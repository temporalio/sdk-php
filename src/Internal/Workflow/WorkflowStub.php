<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
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
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\Exception\Client\WorkflowExecutionAlreadyStartedException;
use Temporal\Exception\Client\WorkflowQueryException;
use Temporal\Exception\Client\WorkflowQueryRejectedException;
use Temporal\Exception\ClientException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\TerminatedFailure;
use Temporal\Exception\Failure\TimeoutFailure;
use Temporal\Exception\IllegalStateException;
use Temporal\Exception\TimeoutException;
use Temporal\Exception\WorkflowExecutionFailedException;
use Temporal\Exception\WorkflowFailedException;
use Temporal\Exception\WorkflowNotFoundException;
use Temporal\Exception\WorkflowServiceException;
use Temporal\Workflow\WorkflowExecution;

final class WorkflowStub implements WorkflowStubInterface
{
    /**
     * @var string
     */
    private const ERROR_WORKFLOW_START_DUPLICATION =
        'Cannot reuse a stub instance to start more than one workflow execution. ' .
        'The stub points to already started execution. If you are trying to wait ' .
        'for a workflow completion either change WorkflowIdReusePolicy from ' .
        'AllowDuplicate or use WorkflowStub.getResult';

    /**
     * @var string
     */
    private const ERROR_WORKFLOW_NOT_STARTED =
        'Method "%s" cannot be called because the workflow has not been started';

    /**
     * @var ServiceClientInterface
     */
    private ServiceClientInterface $serviceClient;

    /**
     * @var ClientOptions
     */
    private ClientOptions $clientOptions;

    /**
     * @var DataConverterInterface
     */
    private DataConverterInterface $dataConverter;

    /**
     * @var string
     */
    private string $workflowType;

    /**
     * @var WorkflowOptions
     */
    private WorkflowOptions $options;

    /**
     * @var WorkflowExecution|null
     */
    private ?WorkflowExecution $execution = null;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions $clientOptions
     * @param DataConverterInterface $dataConverter
     * @param string $workflowType
     * @param WorkflowOptions $options
     */
    public function __construct(
        ServiceClientInterface $serviceClient,
        ClientOptions $clientOptions,
        DataConverterInterface $dataConverter,
        string $workflowType,
        WorkflowOptions $options
    ) {
        $this->serviceClient = $serviceClient;
        $this->clientOptions = $clientOptions;
        $this->dataConverter = $dataConverter;
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
     * {@inheritDoc}
     */
    public function getExecution(): WorkflowExecution
    {
        $this->assertStarted(__FUNCTION__);

        return $this->execution;
    }

    /**
     * {@inheritDoc}
     */
    public function start(...$args): WorkflowExecution
    {
        $r = new StartWorkflowExecutionRequest();
        $r->setRequestId(Uuid::v4());
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);

        $r->setWorkflowId($this->options->workflowId);
        $r->setCronSchedule($this->options->cronSchedule);
        $r->setWorkflowType(new WorkflowType(['name' => $this->workflowType]));
        $r->setTaskQueue(new TaskQueue(['name' => $this->options->taskQueue]));

        if (is_array($this->options->memo)) {
            $memo = new Memo();
            $memo->setFields($this->options->memo);
            $r->setMemo($memo);
        }

        if (is_array($this->options->searchAttributes)) {
            $search = new SearchAttributes();
            $search->setIndexedFields($this->options->searchAttributes);
            $r->setSearchAttributes($search);
        }

        if ($this->options->cronSchedule !== null) {
            $r->setCronSchedule($this->options->cronSchedule);
        }

        if ($this->options->workflowIdReusePolicy !== null) {
            $r->setWorkflowIdReusePolicy($this->options->workflowIdReusePolicy);
        }

        // todo: map retry options
//        if ($this->options->retryOptions !== null) {
//            $ro = new RetryPolicy();
//            $ro->setBackoffCoefficient($this->options->retryOptions->backoffCoefficient);
//
//            if ($this->options->retryOptions->initialInterval !== null) {
//                // todo: has to be updated
//                $ro->setInitialInterval(
//                    new Duration(['seconds' => $this->options->retryOptions->initialInterval->s])
//                );
//            }
//        }
        // todo: map timings

        if ($args !== []) {
            $r->setInput(EncodedValues::createFromValues($args, $this->dataConverter)->toPayloads());
        }

        if ($args !== []) {
            $r->setInput(EncodedValues::createFromValues($args, $this->dataConverter)->toPayloads());
        }

        try {
            $response = $this->serviceClient->StartWorkflowExecution($r);
        } catch (ClientException $e) {
            $f = $e->tryFailure(
                WorkflowExecutionAlreadyStartedFailure::class,
                'Workflow execution is already running'
            );

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
        $r->setRequestId(Uuid::v4());
        $r->setIdentity($this->clientOptions->identity);
        $r->setNamespace($this->clientOptions->namespace);

        $r->setWorkflowId($this->options->workflowId);
        $r->setCronSchedule($this->options->cronSchedule);
        $r->setWorkflowType(new WorkflowType(['name' => $this->workflowType]));
        $r->setTaskQueue(new TaskQueue(['name' => $this->options->taskQueue]));

        if (is_array($this->options->memo)) {
            $memo = new Memo();
            $memo->setFields($this->options->memo);
            $r->setMemo($memo);
        }

        if (is_array($this->options->searchAttributes)) {
            $search = new SearchAttributes();
            $search->setIndexedFields($this->options->searchAttributes);
            $r->setSearchAttributes($search);
        }

        if ($this->options->cronSchedule !== null) {
            $r->setCronSchedule($this->options->cronSchedule);
        }

        if ($this->options->workflowIdReusePolicy !== null) {
            $r->setWorkflowIdReusePolicy($this->options->workflowIdReusePolicy);
        }

        // todo: map retry options
//        if ($this->options->retryOptions !== null) {
//            $ro = new RetryPolicy();
//            $ro->setBackoffCoefficient($this->options->retryOptions->backoffCoefficient);
//
//            if ($this->options->retryOptions->initialInterval !== null) {
//                // todo: has to be updated
//                $ro->setInitialInterval(
//                    new Duration(['seconds' => $this->options->retryOptions->initialInterval->s])
//                );
//            }
//        }


        // todo: map timings

        $r->setSignalName($signal);

        if ($startArgs !== []) {
            $r->setInput(EncodedValues::createFromValues($startArgs, $this->dataConverter)->toPayloads());
        }

        if ($signalArgs !== []) {
            $r->setSignalInput(EncodedValues::createFromValues($signalArgs, $this->dataConverter)->toPayloads());
        }

        try {
            $response = $this->serviceClient->SignalWithStartWorkflowExecution($r);
        } catch (ClientException $e) {
            $f = $e->tryFailure(
                WorkflowExecutionAlreadyStartedFailure::class,
                'Workflow execution is already running'
            );

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
     * Wait for the workflow completion and return it's result.
     *
     * @param int $timeout Timeout in seconds.
     * @param Type|string $returnType
     * @return mixed|null
     *
     * @throws \Throwable
     */
    public function getResult($timeout = null, $returnType = null)
    {
        try {
            $result = $this->fetchResult($timeout);
            if ($result === null || $result->getSize() === 0) {
                return $result;
            }

            return $result->getValue(0, $returnType);
        } catch (TimeoutException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->mapWorkflowFailureToException($e);
        }
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
        $r->setWorkflowExecution($this->getProtoWorkflowExecution());
        $r->setSignalName($name);

        if ($args !== []) {
            $r->setInput(EncodedValues::createFromValues($args, $this->dataConverter)->toPayloads());
        }

        try {
            $this->serviceClient->SignalWorkflowExecution($r);
        } catch (ClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw new WorkflowNotFoundException(null, $this->execution, $this->workflowType, $e);
            } else {
                throw new WorkflowServiceException(null, $this->execution, $this->workflowType, $e);
            }
        } catch (\Throwable $e) {
            throw new WorkflowServiceException(null, $this->execution, $this->workflowType, $e);
        }
    }

    /**
     * @param string $name
     * @param array $args
     * @return mixed|void
     */
    public function query(string $name, array $args = []): ?EncodedValues
    {
        $this->assertStarted(__FUNCTION__);

        $r = new QueryWorkflowRequest();
        $r->setNamespace($this->clientOptions->namespace);
        $r->setExecution($this->getProtoWorkflowExecution());
        $r->setQueryRejectCondition($this->clientOptions->queryRejectionCondition);

        $q = new WorkflowQuery();
        $q->setQueryType($name);
        if ($args !== []) {
            $q->setQueryArgs(EncodedValues::createFromValues($args, $this->dataConverter)->toPayloads());
        }

        $r->setQuery($q);

        try {
            $result = $this->serviceClient->QueryWorkflow($r);
        } catch (ClientException $e) {
            if ($e->getCode() === StatusCode::NOT_FOUND) {
                throw new WorkflowNotFoundException(null, $this->execution, $this->workflowType, $e);
            } elseif ($e->tryFailure(QueryFailedFailure::class, 'query') !== null) {
                // todo: verify properly working
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

            return EncodedValues::createFromPayloads($result->getQueryResult(), $this->dataConverter);
        }

        throw new WorkflowQueryRejectedException(
            $this->execution,
            $this->workflowType,
            $this->clientOptions->queryRejectionCondition,
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
        $r->setWorkflowExecution($this->getProtoWorkflowExecution());

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
        $r->setWorkflowExecution($this->getProtoWorkflowExecution());
        $r->setReason($reason);

        if ($details !== []) {
            $r->setDetails(EncodedValues::createFromValues($details, $this->dataConverter)->toPayloads());
        }

        $this->serviceClient->TerminateWorkflowExecution($r);
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
     * @return \Temporal\Api\Common\V1\WorkflowExecution
     */
    private function getProtoWorkflowExecution(): \Temporal\Api\Common\V1\WorkflowExecution
    {
        $wr = new \Temporal\Api\Common\V1\WorkflowExecution();
        $wr->setWorkflowId($this->execution->id);
        $wr->setRunId($this->execution->runId);

        return $wr;
    }

    /**
     * @param null $timeout
     * @return EncodedValues|null
     */
    private function fetchResult($timeout = null): ?EncodedValues
    {
        $this->assertStarted(__FUNCTION__);

        $closeEvent = $this->getCloseEvent($timeout);

        switch ($closeEvent->getEventType()) {
            case EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED:
                $attr = $closeEvent->getWorkflowExecutionCompletedEventAttributes();

                if (!$attr->hasResult()) {
                    return null;
                }

                return EncodedValues::createFromPayloads($attr->getResult(), $this->dataConverter);
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
                    $details = EncodedValues::createFromPayloads($attr->getDetails(), $this->dataConverter);
                } else {
                    $details = EncodedValues::createFromValues([]);
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
                        null,
                        TimeoutType::TIMEOUT_TYPE_START_TO_CLOSE
                    )
                );

            default:
                throw new \RuntimeException(
                    "Workflow end state is not completed: " . $closeEvent->serializeToJsonString()
                );
        }
    }

    // todo: polish it
    private function getCloseEvent(int $timeout = null): HistoryEvent
    {
        $historyRequest = new GetWorkflowExecutionHistoryRequest();
        $historyRequest
            ->setNamespace($this->clientOptions->namespace)
            ->setWaitNewEvent(true)
            ->setHistoryEventFilterType(HistoryEventFilterType::HISTORY_EVENT_FILTER_TYPE_CLOSE_EVENT)
            ->setExecution($this->getProtoWorkflowExecution());

        // todo: timeouts and retries
        do {
            $response = $this->serviceClient->GetWorkflowExecutionHistory($historyRequest);
            if ($response->getHistory()->getEvents()->count() > 0) {
                /** @var HistoryEvent $closeEvent */
                $closeEvent = $response->getHistory()->getEvents()->offsetGet(0);

                if ($closeEvent->getEventType() === EventType::EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW) {
                    $this->execution = new WorkflowExecution(
                        $this->execution->id,
                        $closeEvent
                            ->getWorkflowExecutionContinuedAsNewEventAttributes()
                            ->getNewExecutionRunId()
                    );

                    $historyRequest->setExecution($this->getProtoWorkflowExecution());
                    continue;
                }

                // todo: TimeoutException

                return $closeEvent;
            }
            // todo: retry
        } while (true);
    }

    /**
     * @param \Throwable $e
     * @throws \Throwable
     */
    private function mapWorkflowFailureToException(\Throwable $e): \Throwable
    {
        return $e;
//        Exception failure, @SuppressWarnings("unused") Class<R> returnType) {
//        Throwable f = CheckedExceptionWrapper.unwrap(failure);
//    if (f instanceof Error) {
//        throw (Error) f;
//    }
//    failure = (Exception) f;
//    if (failure instanceof WorkflowExecutionFailedException) {
//        WorkflowExecutionFailedException executionFailed = (WorkflowExecutionFailedException) failure;
//      Throwable cause =
//            FailureConverter.failureToException(
//                executionFailed.getFailure(), clientOptions.getDataConverter());
//      throw new WorkflowFailedException(
//          execution.get(),
//          workflowType.orElse(null),
//          executionFailed.getWorkflowTaskCompletedEventId(),
//          executionFailed.getRetryState(),
//          cause);
//    } else if (failure instanceof StatusRuntimeException) {
//        StatusRuntimeException sre = (StatusRuntimeException) failure;
//      if (sre.getStatus().getCode() == Status.Code.NOT_FOUND) {
//          throw new WorkflowNotFoundException(execution.get(), workflowType.orElse(null));
//      } else {
//          throw new WorkflowServiceException(execution.get(), workflowType.orElse(null), failure);
//      }
//    } else if (failure instanceof CanceledFailure) {
//        throw (CanceledFailure) failure;
//    } else if (failure instanceof WorkflowException) {
//        throw (WorkflowException) failure;
//    } else {
//        throw new WorkflowServiceException(execution.get(), workflowType.orElse(null), failure);
//    }

    }


    //    public static HistoryEvent getInstanceCloseEvent(
//      WorkflowServiceStubs service,
//      String namespace,
//      WorkflowExecution workflowExecution,
//      Scope metricsScope,
//      long timeout,
//      TimeUnit unit)
//      throws TimeoutException {
//    ByteString pageToken = ByteString.EMPTY;
//    GetWorkflowExecutionHistoryResponse response = null;
//    // TODO: Interrupt service long poll call on timeout and on interrupt
//    long start = System.currentTimeMillis();
//    HistoryEvent event;
//    do {
//      GetWorkflowExecutionHistoryRequest r =
//          GetWorkflowExecutionHistoryRequest.newBuilder()
//              .setNamespace(namespace)
//              .setExecution(workflowExecution)
//              .setHistoryEventFilterType(
//                  HistoryEventFilterType.HISTORY_EVENT_FILTER_TYPE_CLOSE_EVENT)
//              .setWaitNewEvent(true)
//              .setNextPageToken(pageToken)
//              .build();
//      long elapsed = System.currentTimeMillis() - start;
//      Deadline expiration = Deadline.after(unit.toMillis(timeout) - elapsed, TimeUnit.MILLISECONDS);
//      if (expiration.timeRemaining(TimeUnit.MILLISECONDS) > 0) {
//        RpcRetryOptions retryOptions =
//            RpcRetryOptions.newBuilder()
//                .setBackoffCoefficient(1)
//                .setInitialInterval(Duration.ofMillis(1))
//                .setMaximumAttempts(Integer.MAX_VALUE)
//                .setExpiration(Duration.ofMillis(expiration.timeRemaining(TimeUnit.MILLISECONDS)))
//                .addDoNotRetry(Status.Code.INVALID_ARGUMENT, null)
//                .addDoNotRetry(Status.Code.NOT_FOUND, null)
//                .build();
//        response =
//            GrpcRetryer.retryWithResult(
//                retryOptions,
//                () -> {
//                  long elapsedInRetry = System.currentTimeMillis() - start;
//                  Deadline expirationInRetry =
//                      Deadline.after(
//                          unit.toMillis(timeout) - elapsedInRetry, TimeUnit.MILLISECONDS);
//                  return service
//                      .blockingStub()
//                      .withOption(METRICS_TAGS_CALL_OPTIONS_KEY, metricsScope)
//                      .withOption(HISTORY_LONG_POLL_CALL_OPTIONS_KEY, true)
//                      .withDeadline(expirationInRetry)
//                      .getWorkflowExecutionHistory(r);
//                });
//}
//if (response == null || !response.hasHistory()) {
//    continue;
//}
//if (timeout != 0 && System.currentTimeMillis() - start > unit.toMillis(timeout)) {
//    throw new TimeoutException(
//        "WorkflowId="
//        + workflowExecution.getWorkflowId()
//        + ", runId="
//        + workflowExecution.getRunId()
//        + ", timeout="
//        + timeout
//        + ", unit="
//        + unit);
//}
//pageToken = response.getNextPageToken();
//History history = response.getHistory();
//      if (history.getEventsCount() > 0) {
//          event = history.getEvents(0);
//          if (!isWorkflowExecutionCompletedEvent(event)) {
//              throw new RuntimeException("Last history event is not completion event: " + event);
//          }
//          // Workflow called continueAsNew. Start polling the new generation with new runId.
//          if (event.getEventType() == EventType.EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW) {
//              pageToken = ByteString.EMPTY;
//              workflowExecution =
//                  WorkflowExecution.newBuilder()
//                  .setWorkflowId(workflowExecution.getWorkflowId())
//                  .setRunId(
//                      event
//                      .getWorkflowExecutionContinuedAsNewEventAttributes()
//                      .getNewExecutionRunId())
//                  .build();
//              continue;
//          }
//          break;
//      }
//    } while (true);
//    return event;
//  }
}
