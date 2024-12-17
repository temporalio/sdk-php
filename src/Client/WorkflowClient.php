<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Doctrine\Common\Annotations\Reader;
use JetBrains\PhpStorm\Deprecated;
use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\ReaderInterface;
use Temporal\Api\Enums\V1\HistoryEventFilterType;
use Temporal\Api\Workflow\V1\WorkflowExecutionInfo;
use Temporal\Api\Workflowservice\V1\CountWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsRequest;
use Temporal\Client\Common\ClientContextTrait;
use Temporal\Client\Common\Paginator;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\Update\LifecycleStage;
use Temporal\Client\Update\UpdateHandle;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\Workflow\CountWorkflowExecutions;
use Temporal\Client\Workflow\WorkflowExecutionHistory;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Internal\Client\ActivityCompletionClient;
use Temporal\Internal\Client\WorkflowProxy;
use Temporal\Internal\Client\WorkflowRun;
use Temporal\Internal\Client\WorkflowStarter;
use Temporal\Internal\Client\WorkflowStub;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Mapper\WorkflowExecutionInfoMapper;
use Temporal\Internal\Support\Reflection;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowRunInterface;
use Temporal\Workflow\WorkflowStub as WorkflowStubConverter;

class WorkflowClient implements WorkflowClientInterface
{
    use ClientContextTrait;

    private const ERROR_WORKFLOW_START_DUPLICATION =
        'Cannot reuse a stub instance to start more than one workflow execution. ' .
        'The stub points to already started execution. If you are trying to wait ' .
        'for a workflow completion either change WorkflowIdReusePolicy from ' .
        'AllowDuplicate or use WorkflowStub.getResult';

    private ClientOptions $clientOptions;
    private DataConverterInterface $converter;
    private ?WorkflowStarter $starter = null;
    private WorkflowReader $reader;

    /** @var Pipeline<WorkflowClientCallsInterceptor, mixed> */
    private Pipeline $interceptorPipeline;

    public function __construct(
        ServiceClientInterface $serviceClient,
        ?ClientOptions $options = null,
        ?DataConverterInterface $converter = null,
        ?PipelineProvider $interceptorProvider = null,
    ) {
        $this->client = $serviceClient;
        $this->interceptorPipeline = ($interceptorProvider ?? new SimplePipelineProvider())
            ->getPipeline(WorkflowClientCallsInterceptor::class);
        $this->clientOptions = $options ?? new ClientOptions();
        $this->converter = $converter ?? DataConverter::createDefault();
        $this->reader = new WorkflowReader($this->createReader());
    }

    /**
     * @return static
     */
    public static function create(
        ServiceClientInterface $serviceClient,
        ?ClientOptions $options = null,
        ?DataConverterInterface $converter = null,
        ?PipelineProvider $interceptorProvider = null,
    ): self {
        return new self($serviceClient, $options, $converter, $interceptorProvider);
    }

    public function getServiceClient(): ServiceClientInterface
    {
        return $this->client;
    }

    /**
     * Starts workflow in async mode. Returns WorkflowRun object which can be used to wait for the execution result.
     * WorkflowRun objects created by typed workflow stubs will attempt to type the execution result as well.
     *
     * @param object|WorkflowStubInterface $workflow
     * @param mixed ...$args
     */
    public function start($workflow, ...$args): WorkflowRunInterface
    {
        if ($workflow instanceof WorkflowProxy && !$workflow->hasHandler()) {
            throw new InvalidArgumentException('Unable to start workflow without workflow handler');
        }

        $workflowStub = WorkflowStubConverter::fromWorkflow($workflow);

        $returnType = null;
        if ($workflow instanceof WorkflowProxy) {
            $returnType = $workflow->__getReturnType();
            $method = $workflow->getHandlerReflection();

            $args = Reflection::orderArguments($method, $args);
        }

        $workflowType = $workflowStub->getWorkflowType();

        if ($workflowType === null) {
            throw new InvalidArgumentException(
                \sprintf('Unable to start untyped workflow without given workflowType'),
            );
        }

        if ($workflowStub->hasExecution()) {
            throw new InvalidArgumentException(self::ERROR_WORKFLOW_START_DUPLICATION);
        }

        $execution = $this->getStarter()->start(
            $workflowType,
            $workflowStub->getOptions() ?? WorkflowOptions::new(),
            $args,
        );

        $workflowStub->setExecution($execution);

        return new WorkflowRun(
            stub: $workflowStub,
            returnType: $returnType !== null ? Type::create($returnType) : null,
        );
    }

    public function signalWithStart(
        $workflow,
        string $signal,
        array $signalArgs = [],
        array $startArgs = [],
    ): WorkflowRunInterface {
        if ($workflow instanceof WorkflowProxy && !$workflow->hasHandler()) {
            throw new InvalidArgumentException('Unable to start workflow without workflow handler');
        }

        if ($signal === '') {
            throw new InvalidArgumentException('Signal name cannot be empty');
        }

        $workflowStub = WorkflowStubConverter::fromWorkflow($workflow);

        $returnType = null;
        if ($workflow instanceof WorkflowProxy) {
            $returnType = $workflow->__getReturnType();
            $handler = $workflow->getHandlerReflection();

            $startArgs = Reflection::orderArguments($handler, $startArgs);

            $signalReflection = $workflow->findSignalReflection($signal);

            if ($signalReflection !== null) {
                $signalArgs = Reflection::orderArguments($signalReflection, $signalArgs);
            }
        }

        $workflowType = $workflowStub->getWorkflowType();

        if ($workflowType === null) {
            throw new InvalidArgumentException(
                \sprintf('Unable to start untyped workflow without given workflowType'),
            );
        }

        if ($workflowStub->hasExecution()) {
            throw new InvalidArgumentException(self::ERROR_WORKFLOW_START_DUPLICATION);
        }

        $execution = $this->getStarter()->signalWithStart(
            $workflowType,
            $workflowStub->getOptions() ?? WorkflowOptions::new(),
            $signal,
            $signalArgs,
            $startArgs,
        );

        $workflowStub->setExecution($execution);

        return new WorkflowRun(
            stub: $workflowStub,
            returnType: $returnType !== null ? Type::create($returnType) : null,
        );
    }

    #[Deprecated(replacement: '%class%->signalWithStart(%parametersList%)')]
    public function startWithSignal(
        $workflow,
        string $signal,
        array $signalArgs = [],
        array $startArgs = [],
    ): WorkflowRunInterface {
        return $this->signalWithStart($workflow, $signal, $signalArgs, $startArgs);
    }

    public function updateWithStart(
        $workflow,
        string|UpdateOptions $update,
        array $updateArgs = [],
        array $startArgs = [],
    ): UpdateHandle {
        $workflow instanceof WorkflowProxy && !$workflow->hasHandler() && throw new InvalidArgumentException(
            'Unable to start workflow without workflow handler',
        );

        $update = \is_string($update) ? UpdateOptions::new($update, LifecycleStage::StageAccepted) : $update;

        $workflowStub = WorkflowStubConverter::fromWorkflow($workflow);

        $workflowType = $workflowStub->getWorkflowType() ?? throw new InvalidArgumentException(
            'Unable to start untyped workflow without given workflowType',
        );
        $workflowStub->hasExecution() and throw new InvalidArgumentException(self::ERROR_WORKFLOW_START_DUPLICATION);

        [$execution, $handle] = $this->getStarter()->updateWithStart(
            $workflowType,
            $workflowStub->getOptions() ?? WorkflowOptions::new(),
            $update,
            $updateArgs,
            $startArgs,
        );

        $workflowStub->setExecution($execution);

        return $handle instanceof \Throwable
            ? throw $handle
            : $handle;
    }

    public function newWorkflowStub(
        string $class,
        ?WorkflowOptions $options = null,
    ): object {
        $workflow = $this->reader->fromClass($class);

        return new WorkflowProxy(
            $this,
            $this->newUntypedWorkflowStub($workflow->getID(), $options),
            $workflow,
        );
    }

    public function newUntypedWorkflowStub(
        string $workflowType,
        ?WorkflowOptions $options = null,
    ): WorkflowStubInterface {
        $options ??= new WorkflowOptions();

        return new WorkflowStub(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $this->interceptorPipeline,
            $workflowType,
            $options,
        );
    }

    public function newRunningWorkflowStub(string $class, string $workflowID, ?string $runID = null): object
    {
        $workflow = $this->reader->fromClass($class);

        return new WorkflowProxy(
            $this,
            $this->newUntypedRunningWorkflowStub($workflowID, $runID, $workflow->getID()),
            $workflow,
        );
    }

    public function newUntypedRunningWorkflowStub(
        string $workflowID,
        ?string $runID = null,
        ?string $workflowType = null,
    ): WorkflowStubInterface {
        $untyped = new WorkflowStub(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $this->interceptorPipeline,
            $workflowType,
        );
        $untyped->setExecution(new WorkflowExecution($workflowID, $runID));

        return $untyped;
    }

    public function newActivityCompletionClient(): ActivityCompletionClientInterface
    {
        return new ActivityCompletionClient($this->client, $this->clientOptions, $this->converter);
    }

    public function listWorkflowExecutions(
        string $query,
        ?string $namespace = null,
        int $pageSize = 10,
    ): Paginator {
        if ($pageSize <= 0) {
            throw new InvalidArgumentException('Page size must be greater than 0.');
        }
        $namespace ??= $this->clientOptions->namespace;

        $request = (new ListWorkflowExecutionsRequest())
            ->setNamespace($namespace)
            ->setPageSize($pageSize)
            ->setQuery($query);

        $mapper = new WorkflowExecutionInfoMapper($this->converter);
        $loader = function (ListWorkflowExecutionsRequest $request) use ($mapper): \Generator {
            do {
                $response = $this->client->ListWorkflowExecutions($request);
                $nextPageToken = $response->getNextPageToken();

                $page = [];
                foreach ($response->getExecutions() as $message) {
                    \assert($message instanceof WorkflowExecutionInfo);
                    $page[] = $mapper->fromMessage($message);
                }
                yield $page;

                $request->setNextPageToken($nextPageToken);
            } while ($nextPageToken !== '');
        };
        $counter = fn(): int => $this->countWorkflowExecutions($query, $namespace)->count;

        return Paginator::createFromGenerator($loader($request), $counter);
    }

    public function countWorkflowExecutions(
        string $query,
        ?string $namespace = null,
    ): CountWorkflowExecutions {
        $namespace ??= $this->clientOptions->namespace;
        $response = $this->client
            ->CountWorkflowExecutions(
                (new CountWorkflowExecutionsRequest())->setNamespace($namespace)->setQuery($query),
            );

        return new CountWorkflowExecutions(
            count: (int) $response->getCount(),
        );
    }

    public function getWorkflowHistory(
        WorkflowExecution $execution,
        ?string $namespace = null,
        bool $waitNewEvent = false,
        int $historyEventFilterType = HistoryEventFilterType::HISTORY_EVENT_FILTER_TYPE_ALL_EVENT,
        bool $skipArchival = false,
        int $pageSize = 0,
    ): WorkflowExecutionHistory {
        $namespace ??= $this->clientOptions->namespace;
        // Build request
        $request = (new GetWorkflowExecutionHistoryRequest())
            ->setNamespace($namespace)
            ->setWaitNewEvent($waitNewEvent)
            ->setHistoryEventFilterType($historyEventFilterType)
            ->setSkipArchival($skipArchival)
            ->setMaximumPageSize($pageSize)
            ->setExecution(
                (new \Temporal\Api\Common\V1\WorkflowExecution())
                    ->setWorkflowId($execution->getID())
                    ->setRunId(
                        $execution->getRunID() ?? throw new InvalidArgumentException('Execution Run ID is required.'),
                    ),
            );

        $loader = function (GetWorkflowExecutionHistoryRequest $request): \Generator {
            do {
                $response = $this->client->GetWorkflowExecutionHistory($request);
                $nextPageToken = $response->getNextPageToken();

                yield [$response];

                $request->setNextPageToken($nextPageToken);
            } while ($nextPageToken !== '');
        };

        $paginator = Paginator::createFromGenerator($loader($request), null);

        return new WorkflowExecutionHistory($paginator);
    }

    public function __clone()
    {
        $this->starter = null;
    }

    private function createReader(): ReaderInterface
    {
        if (\interface_exists(Reader::class)) {
            return new SelectiveReader([new AnnotationReader(), new AttributeReader()]);
        }

        return new AttributeReader();
    }

    private function getStarter(): WorkflowStarter
    {
        return $this->starter ??= new WorkflowStarter(
            $this->client,
            $this->converter,
            $this->clientOptions,
            $this->interceptorPipeline,
        );
    }
}
