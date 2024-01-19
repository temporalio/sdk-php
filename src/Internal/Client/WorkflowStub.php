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
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowException;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Client\WorkflowQueryException;
use Temporal\Exception\Client\WorkflowQueryRejectedException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\FailureConverter;
use Temporal\Exception\Failure\TerminatedFailure;
use Temporal\Exception\Failure\TimeoutFailure;
use Temporal\Exception\IllegalStateException;
use Temporal\Exception\WorkflowExecutionFailedException;
use Temporal\Interceptor\Header;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\QueryInput;
use Temporal\Interceptor\WorkflowClient\SignalInput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Interceptor\WorkflowClient\UpdateInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Internal\Interceptor\HeaderCarrier;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\Workflow\Update\WaitPolicy;
use Temporal\Workflow\WorkflowExecution;

final class WorkflowStub implements WorkflowStubInterface, HeaderCarrier
{
    private const ERROR_WORKFLOW_NOT_STARTED = 'Method "%s" cannot be called because the workflow has not been started';

    private ?WorkflowExecution $execution = null;
    private HeaderInterface $header;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions $clientOptions
     * @param DataConverterInterface $converter
     * @param Pipeline<WorkflowClientCallsInterceptor, void> $interceptors
     * @param non-empty-string|null $workflowType
     * @param WorkflowOptions|null $options
     */
    public function __construct(
        private ServiceClientInterface $serviceClient,
        private ClientOptions $clientOptions,
        private DataConverterInterface $converter,
        private Pipeline $interceptors,
        private ?string $workflowType = null,
        private ?WorkflowOptions $options = null,
    ) {
        $this->header = Header::empty();
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

    public function getHeader(): HeaderInterface
    {
        return $this->header;
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

        $request = new SignalWorkflowExecutionRequest();
        $request->setRequestId(Uuid::v4());
        $request->setIdentity($this->clientOptions->identity);
        $request->setNamespace($this->clientOptions->namespace);
        $serviceClient = $this->serviceClient;

        $this->interceptors->with(
            static function (SignalInput $input) use ($request, $serviceClient): void {
                $request->setWorkflowExecution($input->workflowExecution->toProtoWorkflowExecution());
                $request->setSignalName($input->signalName);

                if (!$input->arguments->isEmpty()) {
                    $request->setInput($input->arguments->toPayloads());
                }

                try {
                    $serviceClient->SignalWorkflowExecution($request);
                } catch (ServiceClientException $e) {
                    if ($e->getCode() === StatusCode::NOT_FOUND) {
                        throw WorkflowNotFoundException::withoutMessage(
                            $input->workflowExecution,
                            $input->workflowType,
                            $e,
                        );
                    }

                    throw WorkflowServiceException::withoutMessage($input->workflowExecution, $input->workflowType, $e);
                }
            },
            /** @see WorkflowClientCallsInterceptor::signal() */
            'signal',
        )(new SignalInput(
            $this->getExecution(),
            $this->workflowType,
            $name,
            EncodedValues::fromValues($args, $this->converter),
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $name, ...$args): ?EncodedValues
    {
        $this->assertStarted(__FUNCTION__);

        $serviceClient = $this->serviceClient;
        $converter = $this->converter;
        $clientOptions = $this->clientOptions;

        return $this->interceptors->with(
            static function (QueryInput $input) use ($serviceClient, $converter, $clientOptions): ?EncodedValues {
                $request = new QueryWorkflowRequest();
                $request->setNamespace($clientOptions->namespace);
                $request->setQueryRejectCondition($clientOptions->queryRejectionCondition);
                $request->setExecution($input->workflowExecution->toProtoWorkflowExecution());

                $q = new WorkflowQuery();
                $q->setQueryType($input->queryType);

                if (!$input->arguments->isEmpty()) {
                    $q->setQueryArgs($input->arguments->toPayloads());
                }

                $request->setQuery($q);

                try {
                    $result = $serviceClient->QueryWorkflow($request);
                } catch (ServiceClientException $e) {
                    if ($e->getCode() === StatusCode::NOT_FOUND) {
                        throw new WorkflowNotFoundException(null, $input->workflowExecution, $input->workflowType, $e);
                    }

                    if ($e->getFailure(QueryFailedFailure::class) !== null) {
                        throw new WorkflowQueryException(null, $input->workflowExecution, $input->workflowType, $e);
                    }

                    throw new WorkflowServiceException(null, $input->workflowExecution, $input->workflowType, $e);
                } catch (\Throwable $e) {
                    throw new WorkflowServiceException(null, $input->workflowExecution, $input->workflowType, $e);
                }

                if (!$result->hasQueryRejected()) {
                    if (!$result->hasQueryResult()) {
                        return null;
                    }

                    return EncodedValues::fromPayloads($result->getQueryResult(), $converter);
                }

                throw new WorkflowQueryRejectedException(
                    $input->workflowExecution,
                    $input->workflowType,
                    $clientOptions->queryRejectionCondition,
                    $result->getQueryRejected()->getStatus(),
                    null
                );
            },
            /** @see WorkflowClientCallsInterceptor::query() */
            'query',
        )(new QueryInput(
            $this->getExecution(),
            $this->workflowType,
            $name,
            EncodedValues::fromValues($args, $this->converter),
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function update(string $name, ...$args): ?EncodedValues
    {
        $this->assertStarted(__FUNCTION__);

        $serviceClient = $this->serviceClient;
        $converter = $this->converter;
        $clientOptions = $this->clientOptions;

        return $this->interceptors->with(
            static function (UpdateInput $input) use ($serviceClient, $converter, $clientOptions): ?EncodedValues {
                $request = (new UpdateWorkflowExecutionRequest())
                    ->setNamespace($clientOptions->namespace)
                    ->setWorkflowExecution($input->workflowExecution->toProtoWorkflowExecution())
                    ->setRequest($r = new \Temporal\Api\Update\V1\Request());
                    /** todo {@see WaitPolicy} */
                    // ->setWaitPolicy()
                    // ->setFirstExecutionRunId()

                // Configure Meta
                $meta = new \Temporal\Api\Update\V1\Meta();
                $meta->setIdentity($clientOptions->identity);
                // $meta->setUpdateId(...);
                $r->setMeta($meta);

                // Configure update Input
                $i = new \Temporal\Api\Update\V1\Input();
                $i->setName($input->updateType);
                $input->arguments->isEmpty() or $i->setArgs($input->arguments->toPayloads());
                $input->header->isEmpty() or $i->setHeader($input->header->toHeader());
                $r->setInput($i);

                try {
                    $result = $serviceClient->UpdateWorkflowExecution($request);
                } catch (ServiceClientException $e) {
                    if ($e->getCode() === StatusCode::NOT_FOUND) {
                        throw new WorkflowNotFoundException(null, $input->workflowExecution, $input->workflowType, $e);
                    }

                    if ($e->getFailure(QueryFailedFailure::class) !== null) {
                        throw new WorkflowQueryException(null, $input->workflowExecution, $input->workflowType, $e);
                    }

                    throw new WorkflowServiceException(null, $input->workflowExecution, $input->workflowType, $e);
                } catch (\Throwable $e) {
                    throw new WorkflowServiceException(null, $input->workflowExecution, $input->workflowType, $e);
                }

                if (!$result->hasQueryRejected()) {
                    if (!$result->hasQueryResult()) {
                        return null;
                    }

                    return EncodedValues::fromPayloads($result->getQueryResult(), $converter);
                }

                throw new WorkflowQueryRejectedException(
                    $input->workflowExecution,
                    $input->workflowType,
                    $clientOptions->queryRejectionCondition,
                    $result->getQueryRejected()->getStatus(),
                    null
                );
            },
            /** @see WorkflowClientCallsInterceptor::update() */
            'update',
        )(new UpdateInput(
            $this->getExecution(),
            $this->workflowType,
            $name,
            EncodedValues::fromValues($args, $this->converter),
            Header::empty(),
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(): void
    {
        $this->assertStarted(__FUNCTION__);

        $serviceClient = $this->serviceClient;
        $clientOptions = $this->clientOptions;

        $this->interceptors->with(
            static function (CancelInput $input) use ($serviceClient, $clientOptions): void {
                $request = new RequestCancelWorkflowExecutionRequest();
                $request->setRequestId(Uuid::v4());
                $request->setIdentity($clientOptions->identity);
                $request->setNamespace($clientOptions->namespace);
                $request->setWorkflowExecution($input->workflowExecution->toProtoWorkflowExecution());

                $serviceClient->RequestCancelWorkflowExecution($request);
            },
            /** @see WorkflowClientCallsInterceptor::cancel() */
            'cancel',
        )(new CancelInput(
            $this->getExecution(),
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function terminate(string $reason, array $details = []): void
    {
        $this->assertStarted(__FUNCTION__);

        $serviceClient = $this->serviceClient;
        $clientOptions = $this->clientOptions;
        $converter = $this->converter;

        $this->interceptors->with(
            static function (TerminateInput $input) use ($serviceClient, $clientOptions, $details, $converter): void {
                $request = new TerminateWorkflowExecutionRequest();
                $request->setNamespace($clientOptions->namespace);
                $request->setIdentity($clientOptions->identity);
                $request->setWorkflowExecution($input->workflowExecution->toProtoWorkflowExecution());
                $request->setReason($input->reason);

                if ($details !== []) {
                    $request->setDetails(EncodedValues::fromValues($details, $converter)->toPayloads());
                }

                $serviceClient->TerminateWorkflowExecution($request);
            },
            /** @see WorkflowClientCallsInterceptor::terminate() */
            'terminate',
        )(new TerminateInput(
            $this->getExecution(),
            $reason,
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Throwable
     */
    public function getResult($type = null, int $timeout = null): mixed
    {
        $result = $this->interceptors->with(
            function (GetResultInput $input): ?EncodedValues {
                try {
                    return $this->fetchResult($input->timeout);
                } catch (TimeoutException | IllegalStateException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    throw $this->mapWorkflowFailureToException($e);
                }
            },
            /** @see WorkflowClientCallsInterceptor::getResult() */
            'getResult',
        )(new GetResultInput(
            $this->getExecution(),
            $this->workflowType,
            $timeout,
            $type,
        ));

        if ($result === null || $result->count() === 0) {
            return $result;
        }

        return $result->getValue(0, $type);
    }

    /**
     * @param string $method
     * @psalm-assert !null $this->execution
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

                $details = $attr->hasDetails()
                    ? EncodedValues::fromPayloads($attr->getDetails(), $this->converter)
                    : EncodedValues::fromValues([]);

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
