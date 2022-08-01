<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Enums\V1\HistoryEventFilterType;
use Temporal\Api\Enums\V1\RetryState;
use Temporal\Api\Enums\V1\TimeoutType;
use Temporal\Api\Errordetails\V1\QueryFailedFailure;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\Query\V1\WorkflowQuery;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\QueryWorkflowRequest;
use Temporal\Api\Workflowservice\V1\RequestCancelWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest;
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
use Temporal\Exception\Client\WorkflowException;
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
use Temporal\Workflow\WorkflowExecution;

final class WorkflowStub implements WorkflowStubInterface
{
    private const ERROR_WORKFLOW_NOT_STARTED = 'Method "%s" cannot be called because the workflow has not been started';

    private ServiceClientInterface $serviceClient;
    private ClientOptions $clientOptions;
    private DataConverterInterface $converter;
    private ?string $workflowType;
    private ?WorkflowOptions $options;
    private ?WorkflowExecution $execution = null;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions $clientOptions
     * @param DataConverterInterface $converter
     * @param string|null $workflowType
     * @param WorkflowOptions|null $options
     */
    public function __construct(
        ServiceClientInterface $serviceClient,
        ClientOptions $clientOptions,
        DataConverterInterface $converter,
        ?string $workflowType = null,
        WorkflowOptions $options = null
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
    public function getWorkflowType(): ?string
    {
        return $this->workflowType;
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): ?WorkflowOptions
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
     * Connects stub to running workflow.
     *
     * @param WorkflowExecution $execution
     */
    public function setExecution(WorkflowExecution $execution): void
    {
        $this->execution = $execution;
    }

    /**
     * @return bool
     */
    public function hasExecution(): bool
    {
        return $this->execution !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function signal(string $name, ...$args): void
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
    public function query(string $name, ...$args): ?EncodedValues
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
            }

            if ($e->getFailure(QueryFailedFailure::class) !== null) {
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
    public function getResult($type = null, int $timeout = null)
    {
        try {
            $result = $this->fetchResult($timeout);

            if ($result === null || $result->count() === 0) {
                return $result;
            }

            return $result->getValue(0, $type);
        } catch (TimeoutException | IllegalStateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->mapWorkflowFailureToException($e);
        }
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
                        'Workflow canceled',
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
                        '',
                        EncodedValues::empty(),
                        TimeoutType::TIMEOUT_TYPE_START_TO_CLOSE
                    )
                );

            default:
                throw new \RuntimeException(
                    'Workflow end state is not completed: ' . $closeEvent->serializeToJsonString()
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
                    throw new TimeoutException('Unable to wait for workflow completion, deadline reached');
                }
            }

            if ($response->getHistory() === null) {
                continue;
            }

            if ($response->getHistory()->getEvents()->count() === 0) {
                continue;
            }

            /** @var HistoryEvent $closeEvent */
            $closeEvent = $response->getHistory()->getEvents()->offsetGet(0);

            if ($closeEvent->getEventType() === EventType::EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW) {
                $this->execution = new WorkflowExecution(
                    $this->execution->getID(),
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
                }

                return new WorkflowServiceException(
                    null,
                    $this->execution,
                    $this->workflowType,
                    $failure
                );

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
