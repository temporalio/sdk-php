<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\WorkflowExecutionCompletedEventAttributes;
use Temporal\Api\History\V1\WorkflowExecutionContinuedAsNewEventAttributes;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Client\ClientOptions;
use Temporal\Client\Common\RpcRetryOptions;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\ContextInterface;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\GRPC\StatusCode;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Internal\Client\WorkflowStub;
use Temporal\Tests\Fixtures\PipelineProvider;
use Temporal\Workflow\WorkflowExecution;

/**
 * @internal
 */
#[CoversClass(\Temporal\Internal\Client\WorkflowStub::class)]
final class WorkflowStubTestCase extends TestCase
{
    private WorkflowStub $workflowStub;
    /** @var MockObject|ServiceClientInterface */
    private $serviceClient;

    protected function setUp(): void
    {
        $this->serviceClient = $this->createMock(ServiceClientInterface::class);
        $this->workflowStub = new WorkflowStub(
            $this->serviceClient,
            new ClientOptions(),
            $this->createMock(DataConverterInterface::class),
            (new PipelineProvider([]))->getPipeline(WorkflowClientCallsInterceptor::class),
        );
        $this->workflowStub->setExecution(new WorkflowExecution());
    }

    public function testSignalThrowsWorkflowNotFoundException(): void
    {
        $status = new \stdClass();
        $status->details = 'status details';
        $status->code = StatusCode::NOT_FOUND;
        $serviceClientException = new ServiceClientException($status);
        $this->serviceClient
            ->expects(static::once())
            ->method('SignalWorkflowExecution')
            ->willThrowException($serviceClientException);

        static::expectException(WorkflowNotFoundException::class);

        $this->workflowStub->signal('signalName');
    }

    public function testSignalThrowsWorkflowServiceException(): void
    {
        $status = new \stdClass();
        $status->details = 'status details';
        $status->code = StatusCode::INTERNAL;
        $serviceClientException = new ServiceClientException($status);
        $this->serviceClient
            ->expects(static::once())
            ->method('SignalWorkflowExecution')
            ->willThrowException($serviceClientException);

        static::expectException(WorkflowServiceException::class);

        $this->workflowStub->signal('signalName');
    }

    public function testEmptyHistoryContinuesWaitingForHistoryEvents(): void
    {
        $responseWithHistory = (new GetWorkflowExecutionHistoryResponse())
            ->setHistory(
                (new History)->setEvents(
                    [
                        (new HistoryEvent())
                            ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED)
                            ->setWorkflowExecutionCompletedEventAttributes(
                                (new WorkflowExecutionCompletedEventAttributes())->setResult(
                                    (new Payloads())->setPayloads([(new Payload())->setData('hello')])
                                )
                            )
                    ]
                )
            );

        $this->serviceClient
            ->expects(static::exactly(2))
            ->method('GetWorkflowExecutionHistory')
            ->willReturnOnConsecutiveCalls(
                new GetWorkflowExecutionHistoryResponse(),
                $responseWithHistory
            );

        $result = $this->workflowStub->getResult();
        $this->assertNull($result);
    }

    public function testCloseEventReceivedAtDeadlineIsReturned(): void
    {
        $this->serviceClient
            ->expects(static::once())
            ->method('GetWorkflowExecutionHistory')
            ->willReturn(self::completedResponse());

        $this->assertNull($this->workflowStub->getResult(timeout: 0));
    }

    public function testEmptyHistoryAtDeadlineThrowsTimeoutException(): void
    {
        $this->serviceClient
            ->expects(static::once())
            ->method('GetWorkflowExecutionHistory')
            ->willReturn(new GetWorkflowExecutionHistoryResponse());

        static::expectException(TimeoutException::class);

        $this->workflowStub->getResult(timeout: 0);
    }

    public function testEmptyHistoryAfterThePositiveTimeoutThrowsTimeoutException(): void
    {
        $this->serviceClient->method('getContext')->willReturn(Context::default());

        $this->serviceClient
            ->expects(static::once())
            ->method('GetWorkflowExecutionHistory')
            ->willReturnCallback(
                static function (mixed $request, ContextInterface $context): GetWorkflowExecutionHistoryResponse {
                    \time_sleep_until((float) $context->getDeadline()->format('U.u') + 0.001);
                    return new GetWorkflowExecutionHistoryResponse();
                },
            );

        static::expectException(TimeoutException::class);

        $this->workflowStub->getResult(timeout: 1);
    }

    public function testContinueAsNewKeepsTheDeadlineOfTheFirstPoll(): void
    {
        $this->serviceClient->method('getContext')->willReturn(Context::default());

        $continuedAsNew = (new GetWorkflowExecutionHistoryResponse())
            ->setHistory(
                (new History())->setEvents([
                    (new HistoryEvent())
                        ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW)
                        ->setWorkflowExecutionContinuedAsNewEventAttributes(
                            (new WorkflowExecutionContinuedAsNewEventAttributes())->setNewExecutionRunId('next-run'),
                        ),
                ]),
            );
        $deadlines = [];
        $this->serviceClient
            ->expects(static::exactly(2))
            ->method('GetWorkflowExecutionHistory')
            ->with(
                static::anything(),
                static::callback(static function (ContextInterface $context) use (&$deadlines): bool {
                    $deadlines[] = $context->getDeadline()->format('U.u');
                    return true;
                }),
            )
            ->willReturnOnConsecutiveCalls($continuedAsNew, self::completedResponse());

        $this->workflowStub->getResult(timeout: 60);

        $this->assertCount(2, $deadlines);
        $this->assertSame($deadlines[0], $deadlines[1]);
        $this->assertEqualsWithDelta(\microtime(true) + 60, (float) $deadlines[0], 5.0);
    }

    public function testTimeoutKeepsTheContextOfTheClient(): void
    {
        $retryOptions = RpcRetryOptions::new();
        $this->serviceClient
            ->method('getContext')
            ->willReturn(Context::default()->withMetadata(['x-client' => ['value']])->withRetryOptions($retryOptions));

        $this->serviceClient
            ->expects(static::once())
            ->method('GetWorkflowExecutionHistory')
            ->with(
                static::anything(),
                static::callback(
                    static fn(ContextInterface $context): bool => $context->getMetadata() === ['x-client' => ['value']]
                        && $context->getRetryOptions() === $retryOptions,
                ),
            )
            ->willReturn(self::completedResponse());

        $this->workflowStub->getResult(timeout: 60);
    }

    private static function completedResponse(): GetWorkflowExecutionHistoryResponse
    {
        return (new GetWorkflowExecutionHistoryResponse())
            ->setHistory(
                (new History())->setEvents([
                    (new HistoryEvent())
                        ->setEventType(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED)
                        ->setWorkflowExecutionCompletedEventAttributes(new WorkflowExecutionCompletedEventAttributes()),
                ]),
            );
    }
}
