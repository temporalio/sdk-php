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
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Enums\V1\HistoryEventFilterType;
use Temporal\Api\Enums\V1\RetryState;
use Temporal\Api\Enums\V1\TimeoutType;
use Temporal\Api\Errordetails\V1\QueryFailedFailure;
use Temporal\Api\Errordetails\V1\WorkflowExecutionAlreadyStartedFailure;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\Query\V1\WorkflowQuery;
use Temporal\Api\Taskqueue\V1\TaskQueue;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\QueryWorkflowRequest;
use Temporal\Api\Workflowservice\V1\RequestCancelWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\Exception\Client\WorkflowException;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Exception\Client\WorkflowQueryException;
use Temporal\Exception\Client\WorkflowQueryRejectedException;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Exception\Failure\TerminatedFailure;
use Temporal\Exception\Failure\TimeoutFailure;
use Temporal\Exception\IllegalStateException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\WorkflowExecutionFailedException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow\WorkflowExecution;

final class WorkflowStub implements WorkflowStubInterface
{
    private const ERROR_WORKFLOW_START_DUPLICATION =
        'Cannot reuse a stub instance to start more than one workflow execution. ' .
        'The stub points to already started execution. If you are trying to wait ' .
        'for a workflow completion either change WorkflowIdReusePolicy from ' .
        'AllowDuplicate or use WorkflowStub.getResult';

    private const ERROR_WORKFLOW_NOT_STARTED = 'Method "%s" cannot be called because the workflow has not been started';

    private ServiceClientInterface $serviceClient;
    private ClientOptions $clientOptions;
    private DataConverterInterface $converter;
    private string $workflowType;
    private WorkflowOptions $options;
    private ?WorkflowExecution $execution = null;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions $clientOptions
     * @param DataConverterInterface $converter
     * @param string $workflowType
     * @param WorkflowOptions $options
     */
    public function __construct(
        ServiceClientInterface $serviceClient,
        ClientOptions $clientOptions,
        DataConverterInterface $converter,
        string $workflowType,
        WorkflowOptions $options
    ) {
        $this->serviceClient = $serviceClient;
        $this->clientOptions = $clientOptions;
        $this->converter = $converter;
        $this->workflowType = $workflowType;
        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkflowType(): string
    {
        return $this->workflowType;
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): WorkflowOptions
    {
        return $this->options;
    }

    /**
     * @param WorkflowOptions $options
     * @return void
     */
    public function setOptions(WorkflowOptions $options): void
    {
        // todo: replace with merge options
        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function getExecution(): WorkflowExecution
    {
        $this->assertStarted(__FUNCTION__);

        return $this->execution;
    }

    /**
     * Connects stub to running workflow.
     *
     * @param WorkflowExecution $execution
     * @return $this
     */
    public function setExecution(WorkflowExecution $execution): self
    {
        $this->execution = $execution;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function start(array $args = []): WorkflowExecution
    {
        $r = new StartWorkflowExecutionRequest();
        $r
            ->setRequestId(Uuid::v4())
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskQueue(new TaskQueue(['name' => $this->options->taskQueue]))
            ->setWorkflowType(new WorkflowType(['name' => $this->workflowType]))
            ->setWorkflowId($this->options->workflowId)
            ->setCronSchedule($this->options->cronSchedule)
            ->setRetryPolicy($this->options->toRetryPolicy())
            ->setWorkflowIdReusePolicy($this->options->workflowIdReusePolicy)
            ->setWorkflowRunTimeout(DateInterval::toDuration($this->options->workflowRunTimeout))
            ->setWorkflowExecutionTimeout(DateInterval::toDuration($this->options->workflowExecutionTimeout))
            ->setWorkflowTaskTimeout(DateInterval::toDuration($this->options->workflowTaskTimeout));

        if ($this->options->memo !== null) {
            $r->setMemo($this->options->toMemo());
        }

        if ($this->options->searchAttributes !== null) {
            $r->setSearchAttributes($this->options->toSearchAttributes());
        }

        $input = EncodedValues::fromValues($args, $this->converter);
        if (!$input->isEmpty()) {
            $r->setInput($input->toPayloads());
        }

        try {
            $response = $this->serviceClient->StartWorkflowExecution($r);
        } catch (ServiceClientException $e) {
            $f = $e->getFailure(WorkflowExecutionAlreadyStartedFailure::class);

            if ($f instanceof WorkflowExecutionAlreadyStartedFailure) {
                $this->execution = new WorkflowExecution($r->getWorkflowId(), $f->getRunId());

                throw new WorkflowExecutionAlreadyStartedException(
                    $this->execution,
                    $this->workflowType,
                    $e
                );
            }

            throw $e;
        }

        return $this->execution = new WorkflowExecution($this->options->workflowId, $response->getRunId());
    }

    /**
     * {@inheritDoc}
     */
    public function signalWithStart(string $signal, array $signalArgs = [], array $startArgs = []): WorkflowExecution
    {
        $this->assertNotStarted();

        $r = new SignalWithStartWorkflowExecutionRequest();
        $r
            ->setRequestId(Uuid::v4())
            ->setIdentity($this->clientOptions->identity)
            ->setNamespace($this->clientOptions->namespace)
            ->setTaskQueue(new TaskQueue(['name' => $this->options->taskQueue]))
            ->setWorkflowType(new WorkflowType(['name' => $this->workflowType]))
            ->setWorkflowId($this->options->workflowId)
            ->setCronSchedule($this->options->cronSchedule)
            ->setRetryPolicy($this->options->toRetryPolicy())
            ->setWorkflowIdReusePolicy($this->options->workflowIdReusePolicy)
            ->setWorkflowRunTimeout(DateInterval::toDuration($this->options->workflowRunTimeout))
            ->setWorkflowExecutionTimeout(DateInterval::toDuration($this->options->workflowExecutionTimeout))
            ->setWorkflowTaskTimeout(DateInterval::toDuration($this->options->workflowTaskTimeout));

        if ($this->options->memo !== null) {
            $r->setMemo($this->options->toMemo());
        }

        if ($this->options->searchAttributes !== null) {
            $r->setSearchAttributes($this->options->toSearchAttributes());
        }

        $input = EncodedValues::fromValues($startArgs, $this->converter);
        if (!$input->isEmpty()) {
            $r->setInput($input->toPayloads());
        }

        $r->setSignalName($signal);
        $signalInput = EncodedValues::fromValues($signalArgs, $this->converter);
        if (!$signalInput->isEmpty()) {
            $r->setSignalInput($signalInput->toPayloads());
        }

        try {
            $response = $this->serviceClient->SignalWithStartWorkflowExecution($r);
        } catch (ServiceClientException $e) {
            $f = $e->getFailure(WorkflowExecutionAlreadyStartedFailure::class);

            if ($f instanceof WorkflowExecutionAlreadyStartedFailure) {
                $this->execution = new WorkflowExecution($r->getWorkflowId(), $f->getRunId());

                throw new WorkflowExecutionAlreadyStartedException(
                    $this->execution,
                    $this->workflowType,
                    $e
                );
            }

            throw $e;
        }

        return $this->execution = new WorkflowExecution($this->options->workflowId, $response->getRunId());
    }

    /**
     * {@inheritDoc}
     */
    public function signal(string $name, array $args = []): void
    {
        $this->assertStarted(__FUNCTION__);

        $r = new SignalWorkflowExecutionRequest();
        $r->setRequestId(Uuid::v4());
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);

        $r->setWorkflowExecution($this->execution->toProtoWorkflowExecution());
        $r->setSignalName($name);

        $input = EncodedValues::fromValues($args, $this->converter);
        if (!$input->isEmpty()) {
            $r->setInput($input->toPayloads());
        }

        try {
            $this->serviceClient->SignalWorkflowExecution($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw WorkflowNotFoundException::withoutMessage($this->execution, $this->workflowType, $e);
            }

            throw WorkflowServiceException::withoutMessage($this->execution, $this->workflowType, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $name, array $args = []): ?EncodedValues
    {
        $this->assertStarted(__FUNCTION__);

        $r = new QueryWorkflowRequest();
        $r->setNamespace($this->clientOptions->namespace);
        $r->setExecution($this->execution->toProtoWorkflowExecution());
        $r->setQueryRejectCondition($this->clientOptions->queryRejectionCondition);

        $q = new WorkflowQuery();
        $q->setQueryType($name);

        $input = EncodedValues::fromValues($args, $this->converter);
        if (!$input->isEmpty()) {
            $q->setQueryArgs($input->toPayloads());
        }

        $r->setQuery($q);

        try {
            $result = $this->serviceClient->QueryWorkflow($r);
        } catch (ServiceClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw new WorkflowNotFoundException(null, $this->execution, $this->workflowType, $e);
            // TODO Avoid "elseif" stmt
            } elseif ($e->getFailure(QueryFailedFailure::class) !== null) {
                throw new WorkflowQueryException(null, $this->execution, $this->workflowType, $e);
            }

            throw new WorkflowServiceException(null, $this->execution, $this->workflowType, $e);
        } catch (\Throwable $e) {
            throw new WorkflowServiceException(null, $this->execution, $this->workflowType, $e);
        }

        if (!$result->hasQueryRejected()) {
            if (!$result->hasQueryResult()) {
                return null;
            }

            return EncodedValues::fromPayloads($result->getQueryResult(), $this->converter);
        }

        throw new WorkflowQueryRejectedException(
            $this->execution,
            $this->workflowType,
            $this->clientOptions->queryRejectionCondition,
            // TODO Null Pointer Exception
            $result->getQueryRejected()->getStatus(),
            null
        );
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(): void
    {
        $this->assertStarted(__FUNCTION__);

        $r = new RequestCancelWorkflowExecutionRequest();
        $r->setRequestId(Uuid::v4());
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);
        $r->setWorkflowExecution($this->execution->toProtoWorkflowExecution());

        $this->serviceClient->RequestCancelWorkflowExecution($r);
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(string $reason, array $details = []): void
    {
        $this->assertStarted(__FUNCTION__);

        $r = new TerminateWorkflowExecutionRequest();
        $r->setNamespace($this->clientOptions->namespace);
        $r->setIdentity($this->clientOptions->identity);
        $r->setWorkflowExecution($this->execution->toProtoWorkflowExecution());
        $r->setReason($reason);

        if ($details !== []) {
            $r->setDetails(EncodedValues::fromValues($details, $this->converter)->toPayloads());
        }

        $this->serviceClient->TerminateWorkflowExecution($r);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Throwable
     */
    public function getResult($type = null, int $timeout = self::DEFAULT_TIMEOUT)
    {
        try {
            $result = $this->fetchResult($timeout);

            if ($result === null || $result->count() === 0) {
                return $result;
            }

            return $result->getValue(0, $type);
        } catch (TimeoutException|IllegalStateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->mapWorkflowFailureToException($e);
        }
    }

    /**
     * @return void
     */
    private function assertNotStarted(): void
    {
        if ($this->execution === null) {
            return;
        }

        throw new IllegalStateException(self::ERROR_WORKFLOW_START_DUPLICATION);
    }

    /**
     * @param string $method
     */
    private function assertStarted(string $method): void
    {
        if ($this->execution !== null) {
            return;
        }

        throw new IllegalStateException(\sprintf(self::ERROR_WORKFLOW_NOT_STARTED, $method));
    }

    /**
     * @param int|null $timeout
     * @return EncodedValues|null
     * @throws \ErrorException
     */
    private function fetchResult(int $timeout = null): ?EncodedValues
    {
        $this->assertStarted(__FUNCTION__);

        $closeEvent = $this->getCloseEvent($timeout);

        switch ($closeEvent->getEventType()) {
            case EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED:
                $attr = $closeEvent->getWorkflowExecutionCompletedEventAttributes();

                if (!$attr->hasResult()) {
                    return null;
                }

                return EncodedValues::fromPayloads($attr->getResult(), $this->converter);
            case EventType::EVENT_TYPE_WORKFLOW_EXECUTION_FAILED:
                $attr = $closeEvent->getWorkflowExecutionFailedEventAttributes();

                throw new WorkflowExecutionFailedException(
                    $attr->getFailure(),
                    $closeEvent->getTaskId(),
                    $attr->getRetryState()
                );

            case EventType::EVENT_TYPE_WORKFLOW_EXECUTION_CANCELED:
                $attr = $closeEvent->getWorkflowExecutionCanceledEventAttributes();

                if ($attr->hasDetails()) {
                    $details = EncodedValues::fromPayloads($attr->getDetails(), $this->converter);
                } else {
                    $details = EncodedValues::fromValues([]);
                }

                throw new WorkflowFailedException(
                    $this->execution,
                    $this->workflowType,
                    0,
                    RetryState::RETRY_STATE_NON_RETRYABLE_FAILURE,
                    new CanceledFailure(
                        "Workflow canceled",
                        $details,
                        null
                    )
                );
            case EventType::EVENT_TYPE_WORKFLOW_EXECUTION_TERMINATED:
                $attr = $closeEvent->getWorkflowExecutionTerminatedEventAttributes();

                throw new WorkflowFailedException(
                    $this->execution,
                    $this->workflowType,
                    0,
                    RetryState::RETRY_STATE_NON_RETRYABLE_FAILURE,
                    new TerminatedFailure($attr->getReason())
                );

            case EventType::EVENT_TYPE_WORKFLOW_EXECUTION_TIMED_OUT:
                $attr = $closeEvent->getWorkflowExecutionTimedOutEventAttributes();

                throw new WorkflowFailedException(
                    $this->execution,
                    $this->workflowType,
                    0,
                    $attr->getRetryState(),
                    new TimeoutFailure(
                        "",
                        EncodedValues::empty(),
                        TimeoutType::TIMEOUT_TYPE_START_TO_CLOSE
                    )
                );

            default:
                throw new \RuntimeException(
                    "Workflow end state is not completed: " . $closeEvent->serializeToJsonString()
                );
        }
    }

    /**
     * @param int|null $timeout
     * @return HistoryEvent
     * @throws \ErrorException
     */
    private function getCloseEvent(int $timeout = null): HistoryEvent
    {
        $historyRequest = new GetWorkflowExecutionHistoryRequest();
        $historyRequest
            ->setNamespace($this->clientOptions->namespace)
            ->setWaitNewEvent(true)
            ->setHistoryEventFilterType(HistoryEventFilterType::HISTORY_EVENT_FILTER_TYPE_CLOSE_EVENT)
            ->setExecution($this->execution->toProtoWorkflowExecution());

        do {
            $start = time();
            $response = $this->serviceClient->GetWorkflowExecutionHistory(
                $historyRequest,
                $timeout === null ? null : Context::default()->withTimeout($timeout)
            );
            $elapsed = time() - $start;

            if ($timeout !== null) {
                $timeout = max(0, $timeout - $elapsed);

                if ($timeout === 0) {
                    throw new TimeoutException("Unable to wait for workflow completion, deadline reached");
                }
            }

            // TODO Null Pointer Exception
            if ($response->getHistory()->getEvents()->count() === 0) {
                continue;
            }

            // TODO Null Pointer Exception
            /** @var HistoryEvent $closeEvent */
            $closeEvent = $response->getHistory()->getEvents()->offsetGet(0);

            if ($closeEvent->getEventType() === EventType::EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW) {
                $this->execution = new WorkflowExecution(
                    $this->execution->id,
                    $closeEvent
                        ->getWorkflowExecutionContinuedAsNewEventAttributes()
                        ->getNewExecutionRunId()
                );

                $historyRequest->setExecution($this->execution->toProtoWorkflowExecution());
                continue;
            }

            return $closeEvent;
        } while (true);
    }

    /**
     * @param \Throwable $failure
     * @return \Throwable
     */
    private function mapWorkflowFailureToException(\Throwable $failure): \Throwable
    {
        switch (true) {
            case $failure instanceof WorkflowExecutionFailedException:
                return new WorkflowFailedException(
                    $this->execution,
                    $this->workflowType,
                    $failure->getWorkflowTaskCompletedEventId(),
                    $failure->getRetryState(),
                    FailureConverter::mapFailureToException($failure->getFailure(), $this->converter)
                );

            case $failure instanceof ServiceClientException:
                if ($failure->getStatus()->getCode() === StatusCode::NOT_FOUND) {
                    return new WorkflowNotFoundException(
                        null,
                        $this->execution,
                        $this->workflowType,
                        $failure
                    );
                // TODO Avoid "else" stmt
                } else {
                    return new WorkflowServiceException(
                        null,
                        $this->execution,
                        $this->workflowType,
                        $failure
                    );
                }

            case $failure instanceof CanceledFailure || $failure instanceof WorkflowException:
                return $failure;

            default:
                return new WorkflowServiceException(
                    null,
                    $this->execution,
                    $this->workflowType,
                    $failure,
                );
        }
    }
}
