<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Api\Deployment\V1\WorkerDeploymentVersion;
use Temporal\Api\Errordetails\V1\MultiOperationExecutionFailure;
use Temporal\Api\Errordetails\V1\WorkflowExecutionAlreadyStartedFailure;
use Temporal\Api\Failure\V1\MultiOperationExecutionAborted;
use Temporal\Api\Sdk\V1\UserMetadata;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Update\V1\Request as UpdateRequestMessage;
use Temporal\Api\Workflow\V1\VersioningOverride;
use Temporal\Api\Workflow\V1\VersioningOverride\PinnedOverride;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest\Operation;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationResponse\Response;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\Update\UpdateHandle;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\Uuid;
use Temporal\Common\Versioning\VersioningBehavior;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\MultyOperation\OperationStatus;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\UpdateInput;
use Temporal\Interceptor\WorkflowClient\UpdateWithStartInput;
use Temporal\Interceptor\WorkflowClient\UpdateWithStartOutput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow\WorkflowExecution;

/**
 * @internal
 */
final class WorkflowStarter
{
    /**
     * @param Pipeline<WorkflowClientCallsInterceptor, WorkflowExecution> $interceptors
     */
    public function __construct(
        private ServiceClientInterface $serviceClient,
        private DataConverterInterface $converter,
        private ClientOptions $clientOptions,
        private Pipeline $interceptors,
    ) {}

    /**
     * @throws ServiceClientException
     * @throws WorkflowExecutionAlreadyStartedException
     */
    public function start(
        string $workflowType,
        WorkflowOptions $options,
        array $args = [],
    ): WorkflowExecution {
        $header = Header::empty();
        $arguments = EncodedValues::fromValues($args, $this->converter);

        return $this->interceptors->with(
            fn(StartInput $input): WorkflowExecution => $this->executeRequest(
                $this->configureExecutionRequest(new StartWorkflowExecutionRequest(), $input),
            ),
            /** @see WorkflowClientCallsInterceptor::start() */
            'start',
        )(new StartInput($options->workflowId, $workflowType, $header, $arguments, $options));
    }

    /**
     * @param non-empty-string $workflowType
     * @param non-empty-string $signal
     *
     * @throws ServiceClientException
     * @throws WorkflowExecutionAlreadyStartedException
     */
    public function signalWithStart(
        string $workflowType,
        WorkflowOptions $options,
        string $signal,
        array $signalArgs = [],
        array $startArgs = [],
    ): WorkflowExecution {
        $header = Header::empty();
        $arguments = EncodedValues::fromValues($startArgs, $this->converter);
        $signalArguments = EncodedValues::fromValues($signalArgs, $this->converter);

        return $this->interceptors->with(
            function (SignalWithStartInput $input): WorkflowExecution {
                $request = $this->configureExecutionRequest(
                    new SignalWithStartWorkflowExecutionRequest(),
                    $input->workflowStartInput,
                );

                $request->setSignalName($input->signalName);
                if (!$input->signalArguments->isEmpty()) {
                    $request->setSignalInput($input->signalArguments->toPayloads());
                }


                return $this->executeRequest($request);
            },
            /** @see WorkflowClientCallsInterceptor::signalWithStart() */
            'signalWithStart',
        )(
            new SignalWithStartInput(
                new StartInput($options->workflowId, $workflowType, $header, $arguments, $options),
                $signal,
                $signalArguments,
            ),
        );
    }

    /**
     * @param non-empty-string $workflowType
     */
    public function updateWithStart(
        string $workflowType,
        WorkflowOptions $options,
        UpdateOptions $update,
        array $updateArgs = [],
        array $startArgs = [],
    ): UpdateWithStartOutput {
        $arguments = EncodedValues::fromValues($startArgs, $this->converter);
        $updateArguments = EncodedValues::fromValues($updateArgs, $this->converter);

        return $this->interceptors->with(
            function (UpdateWithStartInput $input): UpdateWithStartOutput {
                $startRequest = $this->configureExecutionRequest(
                    new StartWorkflowExecutionRequest(),
                    $input->workflowStartInput,
                );

                $updateRequest = (new UpdateWorkflowExecutionRequest())
                    ->setNamespace($this->clientOptions->namespace)
                    ->setWorkflowExecution($input->updateInput->workflowExecution->toProtoWorkflowExecution())
                    ->setRequest($r = new UpdateRequestMessage())
                    ->setWaitPolicy(
                        (new \Temporal\Api\Update\V1\WaitPolicy())
                            ->setLifecycleStage($input->updateInput->waitPolicy->lifecycleStage->value),
                    );

                // Configure Meta
                $meta = new \Temporal\Api\Update\V1\Meta();
                $meta->setIdentity($this->clientOptions->identity);
                $meta->setUpdateId($input->updateInput->updateId);
                $r->setMeta($meta);

                // Configure update Input
                $i = new \Temporal\Api\Update\V1\Input();
                $i->setName($input->updateInput->updateName);
                $input->updateInput->arguments->setDataConverter($this->converter);
                $input->updateInput->arguments->isEmpty() or $i->setArgs($input->updateInput->arguments->toPayloads());
                $input->updateInput->header->isEmpty() or $i->setHeader($input->updateInput->header->toHeader());
                $r->setInput($i);

                $ops = [
                    (new Operation())->setStartWorkflow($startRequest),
                    (new Operation())->setUpdateWorkflow($updateRequest),
                ];

                try {
                    $response = $this->serviceClient->ExecuteMultiOperation(
                        (new ExecuteMultiOperationRequest())
                            ->setNamespace($this->clientOptions->namespace)
                            ->setOperations($ops),
                    );
                } catch (ServiceClientException $e) {
                    $failure = $e->getFailure(MultiOperationExecutionFailure::class) ?? throw $e;
                    /** @var \ArrayAccess<MultiOperationExecutionFailure\OperationStatus> $fails */
                    $fails = $failure->getStatuses();

                    $updateStatus = isset($fails[1]) ? OperationStatus::fromMessage($fails[1]) : null;
                    if ($updateStatus?->getFailure(MultiOperationExecutionAborted::class)) {
                        $startStatus = OperationStatus::fromMessage($fails[0]);
                        if ($f = $startStatus?->getFailure(WorkflowExecutionAlreadyStartedFailure::class)) {
                            \assert($f instanceof WorkflowExecutionAlreadyStartedFailure);
                            $execution = new WorkflowExecution($input->workflowStartInput->workflowId, $f->getRunId());

                            throw new WorkflowExecutionAlreadyStartedException(
                                $execution,
                                $input->workflowStartInput->workflowType,
                                $e,
                            );
                        }

                        throw $e;
                    }

                    throw new WorkflowServiceException(
                        $updateStatus?->getMessage(),
                        $input->updateInput->workflowExecution,
                        $input->workflowStartInput->workflowType,
                        $e,
                    );
                }

                // Extract result
                /** @var \ArrayAccess<int, Response> $responses */
                $responses = $response->getResponses();

                // Start Workflow: get execution
                $startResponse = $responses[0]->getStartWorkflow();
                \assert($startResponse !== null);
                $execution = new WorkflowExecution($input->workflowStartInput->workflowId, $startResponse->getRunId());

                // Update Workflow: get handler
                $updateResponse = $responses[1]->getUpdateWorkflow();
                \assert($updateResponse !== null);

                try {
                    $updateResult = (new \Temporal\Internal\Client\ResponseToResultMapper($this->converter))
                        ->mapUpdateWorkflowResponse(
                            $updateResponse,
                            updateName: $input->updateInput->updateName,
                            workflowType: $input->workflowStartInput->workflowType,
                            workflowExecution: $execution,
                        );
                } catch (\RuntimeException $e) {
                    return new UpdateWithStartOutput($execution, $e);
                }

                return new UpdateWithStartOutput(
                    $execution,
                    new UpdateHandle(
                        client: $this->serviceClient,
                        clientOptions: $this->clientOptions,
                        converter: $this->converter,
                        execution: $updateResult->getReference()->workflowExecution,
                        workflowType: $input->updateInput->workflowType,
                        updateName: $input->updateInput->updateName,
                        resultType: $input->updateInput->resultType,
                        updateId: $updateResult->getReference()->updateId,
                        result: $updateResult->getResult(),
                    ),
                );
            },
            /** @see WorkflowClientCallsInterceptor::updateWithStart() */
            'updateWithStart',
        )(
            new UpdateWithStartInput(
                new StartInput($options->workflowId, $workflowType, Header::empty(), $arguments, $options),
                new UpdateInput(
                    new WorkflowExecution($options->workflowId),
                    $workflowType,
                    $update->updateName,
                    $updateArguments,
                    Header::empty(),
                    $update->waitPolicy,
                    $update->updateId ?? Uuid::v4(),
                    '',
                    null, // todo?
                ),
            ),
        );
    }

    /**
     * @param StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest $request
     *        use {@see configureExecutionRequest()} to prepare request
     *
     * @throws ServiceClientException
     * @throws WorkflowExecutionAlreadyStartedException
     */
    private function executeRequest(
        StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest $request,
    ): WorkflowExecution {
        try {
            $response = $request instanceof StartWorkflowExecutionRequest
                ? $this->serviceClient->StartWorkflowExecution($request)
                : $this->serviceClient->SignalWithStartWorkflowExecution($request);
        } catch (ServiceClientException $e) {
            $f = $e->getFailure(WorkflowExecutionAlreadyStartedFailure::class) ?? throw $e;

            \assert($f instanceof WorkflowExecutionAlreadyStartedFailure);
            $execution = new WorkflowExecution($request->getWorkflowId(), $f->getRunId());

            throw new WorkflowExecutionAlreadyStartedException(
                $execution,
                $request->getWorkflowType()->getName(),
                $e,
            );
        }

        return new WorkflowExecution(
            $request->getWorkflowId(),
            $response->getRunId(),
        );
    }

    /**
     * @template TRequest of StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest
     *
     * @param TRequest $req
     *
     * @return TRequest
     *
     * @throws \Exception
     */
    private function configureExecutionRequest(
        StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest $req,
        StartInput $input,
    ): StartWorkflowExecutionRequest|SignalWithStartWorkflowExecutionRequest {
        $options = $input->options;

        $req->setRequestId(Uuid::v4())
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskQueue(new TaskQueue(['name' => $options->taskQueue]))
            ->setWorkflowType(new WorkflowType(['name' => $input->workflowType]))
            ->setWorkflowId($input->workflowId)
            ->setCronSchedule($options->cronSchedule ?? '')
            ->setWorkflowIdReusePolicy($options->workflowIdReusePolicy)
            ->setWorkflowIdConflictPolicy($options->workflowIdConflictPolicy->value)
            ->setWorkflowRunTimeout(DateInterval::toDuration($options->workflowRunTimeout))
            ->setWorkflowExecutionTimeout(DateInterval::toDuration($options->workflowExecutionTimeout))
            ->setWorkflowTaskTimeout(DateInterval::toDuration($options->workflowTaskTimeout))
            ->setPriority($options->priority->toProto());

        // Versioning override
        if ($options->versioningOverride !== null) {
            $value = new VersioningOverride();

            if ($options->versioningOverride->behavior === VersioningBehavior::Pinned) {
                $version = $options->versioningOverride->version;
                \assert($version !== null);

                $value->setPinned(
                    (new PinnedOverride())
                        ->setBehavior(VersioningBehavior::Pinned->value)
                        ->setVersion(
                            (new WorkerDeploymentVersion())
                                ->setBuildId($version->buildId)
                                ->setDeploymentName($version->deploymentName),
                        ),
                );
            } elseif ($options->versioningOverride->behavior === VersioningBehavior::AutoUpgrade) {
                $value->setAutoUpgrade(true);
            }

            $req->setVersioningOverride($value);
        }

        // Retry Policy
        $options->retryOptions === null or $req->setRetryPolicy($options->retryOptions->toWorkflowRetryPolicy());

        // Memo
        $memo = $options->toMemo($this->converter);
        $memo === null or $req->setMemo($memo);

        // Search Attributes
        $searchAttributes = $options->toSearchAttributes($this->converter);
        $searchAttributes === null or $req->setSearchAttributes($searchAttributes);

        // Header
        $header = $input->header;
        \assert($header instanceof Header);
        if ($header->count() > 0) {
            $header->setDataConverter($this->converter);
            $req->setHeader($header->toHeader());
        }

        // User metadata
        if ($options->staticSummary !== '' || $options->staticDetails !== '') {
            $metadata = (new UserMetadata());
            $options->staticSummary === '' or $metadata->setSummary($this->converter->toPayload($options->staticSummary));
            $options->staticDetails === '' or $metadata->setDetails($this->converter->toPayload($options->staticDetails));
            $req->setUserMetadata($metadata);
        }

        // Start Delay
        $delay = DateInterval::toDuration($options->workflowStartDelay, true);
        $delay === null or $req->setWorkflowStartDelay($delay);

        if ($req instanceof StartWorkflowExecutionRequest) {
            $req->setRequestEagerExecution($options->eagerStart);
        }

        if (!$input->arguments->isEmpty()) {
            $req->setInput($input->arguments->toPayloads());
        }

        return $req;
    }
}
