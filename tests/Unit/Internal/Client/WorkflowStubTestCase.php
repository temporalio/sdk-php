<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Client;

use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\History\V1\WorkflowExecutionCompletedEventAttributes;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\GRPC\StatusCode;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Internal\Client\WorkflowStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Temporal\Workflow\WorkflowExecution;

/**
 * @internal
 *
 * @covers \Temporal\Internal\Client\WorkflowStub
 */
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
}
