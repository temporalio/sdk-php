<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Psr\Log\LoggerInterface;
use Temporal\Api\Common\V1\ActivityType;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Errordetails\V1\ActivityExecutionAlreadyStartedFailure;
use Temporal\Api\Sdk\V1\UserMetadata;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\CountActivityExecutionsRequest;
use Temporal\Api\Workflowservice\V1\ListActivityExecutionsRequest;
use Temporal\Api\Workflowservice\V1\StartActivityExecutionRequest;
use Temporal\Client\Activity\ActivityHandle;
use Temporal\Client\Activity\CountActivityExecutions;
use Temporal\Client\Activity\StartActivityOptions;
use Temporal\Client\Common\ClientContextTrait;
use Temporal\Client\Common\Paginator;
use Temporal\Client\GRPC\BaseClient;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Common\PayloadLimitOptions;
use Temporal\Common\Uuid;
use Temporal\DataConverter\ActivitySerializationContext;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ActivityAlreadyStartedException;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Interceptor\ActivityClient\StartInput;
use Temporal\Interceptor\ActivityClient\StartOutput;
use Temporal\Interceptor\ActivityClientCallsInterceptor;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Client\ActivityCompletionClient;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Mapper\ActivityExecutionMapper;
use Temporal\Internal\Support\DateInterval;
use Temporal\Plugin\ClientPluginContext;
use Temporal\Plugin\ClientPluginInterface;
use Temporal\Plugin\CompositePipelineProvider;
use Temporal\Plugin\ConnectionPluginInterface;
use Temporal\Plugin\PluginRegistry;
use Temporal\Worker\Logger\StderrLogger;

final class ActivityClient implements ActivityClientInterface
{
    use ClientContextTrait;

    private ClientOptions $clientOptions;
    private DataConverterInterface $converter;

    /** @var Pipeline<ActivityClientCallsInterceptor, mixed> */
    private Pipeline $interceptorPipeline;

    public function __construct(
        ServiceClientInterface $serviceClient,
        ?ClientOptions $options = null,
        ?DataConverterInterface $converter = null,
        ?PipelineProvider $interceptorProvider = null,
        ?PluginRegistry $pluginRegistry = null,
        ?LoggerInterface $logger = null,
    ) {
        $pluginRegistry ??= new PluginRegistry();
        $this->clientOptions = $options ?? new ClientOptions();
        $this->converter = $converter ?? DataConverter::createDefault();

        $connectionPlugins = $pluginRegistry->getPlugins(ConnectionPluginInterface::class);
        $serviceClient = Pipeline::prepare($connectionPlugins)
            ->with(static fn(ServiceClientInterface $serviceClient) => $serviceClient, 'configureServiceClient')($serviceClient);

        $pluginContext = new ClientPluginContext(
            clientOptions: $this->clientOptions,
            dataConverter: $this->converter,
        );
        Pipeline::prepare($pluginRegistry->getPlugins(ClientPluginInterface::class))
            ->with(static fn(ClientPluginContext $pluginContext) => $pluginContext, 'configureClient')($pluginContext);

        $this->clientOptions = $pluginContext->getClientOptions();
        $this->converter = $pluginContext->getDataConverter() ?? $this->converter;
        $this->interceptorPipeline = (new CompositePipelineProvider(
            $pluginContext->getInterceptors(),
            $interceptorProvider ?? new SimplePipelineProvider(),
        ))->getPipeline(ActivityClientCallsInterceptor::class);

        if ($serviceClient instanceof BaseClient) {
            $serviceClient = $serviceClient->withDefaultPayloadLimits(
                $this->clientOptions->payloadLimits ?? PayloadLimitOptions::new(),
                $logger ?? new StderrLogger(),
            );
        }

        $context = $serviceClient->getContext();
        $this->client = $serviceClient->withContext(
            $context->withMetadata(
                ['Temporal-Namespace' => [$this->clientOptions->namespace]] + $context->getMetadata(),
            ),
        );
    }

    public static function create(
        ServiceClientInterface $serviceClient,
        ?ClientOptions $options = null,
        ?DataConverterInterface $converter = null,
        ?PipelineProvider $interceptorProvider = null,
        ?PluginRegistry $pluginRegistry = null,
        ?LoggerInterface $logger = null,
    ): ActivityClientInterface {
        return new self($serviceClient, $options, $converter, $interceptorProvider, $pluginRegistry, $logger);
    }

    public function start(string $activityType, StartActivityOptions $options, mixed ...$args): ActivityHandle
    {
        $context = new ActivitySerializationContext(
            namespace: $this->clientOptions->namespace,
            activityType: $activityType,
            taskQueue: $options->taskQueue,
        );

        $startInput = new StartInput(
            $activityType,
            Header::empty(),
            EncodedValues::fromValues($args, $this->converter)->withSerializationContext($context),
            $options,
        );

        $output = $this->interceptorPipeline->with(
            function (StartInput $input) use (&$startInput): StartOutput {
                $startInput = $input;

                return $this->executeStart($input);
            },
            'start',
        )($startInput);
        \assert($output instanceof StartOutput);

        return $this->createHandle(
            $this->clientOptions->namespace,
            $output->activityId,
            $output->runId,
            $output->started
                ? new ActivitySerializationContext(
                    namespace: $this->clientOptions->namespace,
                    activityType: $startInput->activityType,
                    taskQueue: $startInput->options->taskQueue,
                )
                : null,
        );
    }

    public function getHandle(string $activityId, ?string $runId = null, ?string $namespace = null): ActivityHandle
    {
        return $this->createHandle($namespace ?? $this->clientOptions->namespace, $activityId, $runId);
    }

    public function listExecutions(string $query, ?string $namespace = null, int $pageSize = 10): Paginator
    {
        if ($pageSize <= 0) {
            throw new \InvalidArgumentException('Page size must be greater than 0.');
        }
        $namespace ??= $this->clientOptions->namespace;

        $request = (new ListActivityExecutionsRequest())
            ->setNamespace($namespace)
            ->setPageSize($pageSize)
            ->setQuery($query);

        $mapper = new ActivityExecutionMapper($this->converter, $namespace);
        $loader = function (ListActivityExecutionsRequest $request) use ($mapper): \Generator {
            do {
                $response = $this->client->ListActivityExecutions($request);
                $nextPageToken = $response->getNextPageToken();

                $page = [];
                /** @psalm-suppress TooManyTemplateParams */
                foreach ($response->getExecutions() as $message) {
                    $page[] = $mapper->fromListInfo($message);
                }
                yield $page;

                $request->setNextPageToken($nextPageToken);
            } while ($nextPageToken !== '');
        };
        $counter = fn(): int => \max(0, $this->countExecutions($query, $namespace)->count);

        return Paginator::createFromGenerator($loader($request), $counter);
    }

    public function countExecutions(string $query, ?string $namespace = null): CountActivityExecutions
    {
        $response = $this->client->CountActivityExecutions(
            (new CountActivityExecutionsRequest())
                ->setNamespace($namespace ?? $this->clientOptions->namespace)
                ->setQuery($query),
        );

        return new CountActivityExecutions(count: (int) $response->getCount());
    }

    public function newActivityCompletionClient(): ActivityCompletionClientInterface
    {
        return new ActivityCompletionClient($this->client, $this->clientOptions, $this->converter);
    }

    private function executeStart(StartInput $input): StartOutput
    {
        $options = $input->options;
        if ($options->id === '') {
            throw new \InvalidArgumentException('Activity ID is required.');
        }
        if ($options->taskQueue === '') {
            throw new \InvalidArgumentException('Task queue is required.');
        }
        if (DateInterval::toDuration($options->scheduleToCloseTimeout, true) === null
            && DateInterval::toDuration($options->startToCloseTimeout, true) === null) {
            throw new \InvalidArgumentException(
                'At least one of schedule to close or start to close timeouts is required.',
            );
        }

        $request = (new StartActivityExecutionRequest())
            ->setNamespace($this->clientOptions->namespace)
            ->setIdentity($this->clientOptions->identity)
            ->setRequestId(Uuid::v4())
            ->setActivityId($options->id)
            ->setActivityType(new ActivityType(['name' => $input->activityType]))
            ->setTaskQueue(new TaskQueue(['name' => $options->taskQueue]))
            ->setScheduleToCloseTimeout(DateInterval::toDuration($options->scheduleToCloseTimeout))
            ->setScheduleToStartTimeout(DateInterval::toDuration($options->scheduleToStartTimeout))
            ->setStartToCloseTimeout(DateInterval::toDuration($options->startToCloseTimeout))
            ->setHeartbeatTimeout(DateInterval::toDuration($options->heartbeatTimeout))
            ->setIdReusePolicy($options->idReusePolicy->value)
            ->setIdConflictPolicy($options->idConflictPolicy->value)
            ->setPriority($options->priority->toProto());

        $options->retryOptions === null or $request->setRetryPolicy($options->retryOptions->toWorkflowRetryPolicy());

        $delay = DateInterval::toDuration($options->startDelay, true);
        $delay === null or $request->setStartDelay($delay);

        if ($options->typedSearchAttributes !== null && $options->typedSearchAttributes->count() > 0) {
            $fields = [];
            foreach ($options->typedSearchAttributes as $key => $value) {
                $fields[$key->getName()] = $this->converter->toPayload($value);
            }
            $request->setSearchAttributes((new SearchAttributes())->setIndexedFields($fields));
        }

        if ($options->staticSummary !== '' || $options->staticDetails !== '') {
            $metadata = new UserMetadata();
            $options->staticSummary === '' or $metadata->setSummary($this->converter->toPayload($options->staticSummary));
            $options->staticDetails === '' or $metadata->setDetails($this->converter->toPayload($options->staticDetails));
            $request->setUserMetadata($metadata);
        }

        $header = $input->header;
        if ($header->count() > 0) {
            \assert($header instanceof Header);
            $header->setDataConverter($this->converter);
            $request->setHeader($header->toHeader());
        }

        $input->arguments->isEmpty() or $request->setInput($input->arguments->toPayloads());

        try {
            $response = $this->client->StartActivityExecution($request);
        } catch (ServiceClientException $e) {
            $failure = $e->getFailure(ActivityExecutionAlreadyStartedFailure::class) ?? throw $e;
            \assert($failure instanceof ActivityExecutionAlreadyStartedFailure);

            throw new ActivityAlreadyStartedException(
                $options->id,
                $input->activityType,
                $failure->getRunId() === '' ? null : $failure->getRunId(),
                $e,
            );
        }

        $runId = $response->getRunId();

        return new StartOutput($options->id, $runId === '' ? null : $runId, $response->getStarted());
    }

    private function createHandle(
        string $namespace,
        string $activityId,
        ?string $runId,
        ?ActivitySerializationContext $context = null,
    ): ActivityHandle {
        return new ActivityHandle(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $this->interceptorPipeline,
            $namespace,
            $activityId,
            $runId,
            $context,
        );
    }
}
