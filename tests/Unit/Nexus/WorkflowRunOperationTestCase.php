<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\Nexus\Handler\OperationStartDetails;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use PHPUnit\Framework\MockObject\MockObject;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Internal\Nexus\NexusContext;
use Temporal\Nexus\Internal\WorkflowRunOperationToken;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * Mocks the WorkflowClient and exercises the {@see WorkflowRunOperation}
 * helper end-to-end (sans the actual Temporal server).
 *
 * @group unit
 * @group nexus
 */
final class WorkflowRunOperationTestCase extends AbstractUnit
{
    private const NS = 'sample-ns';
    private const WID = 'sample-wid';

    /** @var WorkflowClientInterface&MockObject */
    private WorkflowClientInterface $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(WorkflowClientInterface::class);
        Nexus::setCurrentContext(new NexusContext(
            operation: new NexusOperationContext(self::NS, 'tq', $this->client),
            current: new OperationContext(service: 'svc', operation: 'op'),
        ));
    }

    protected function tearDown(): void
    {
        Nexus::setCurrentContext(null);
    }

    public function testStartReturnsAsyncTokenAndStartsWorkflow(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);

        $capturedOptions = null;
        $this->client->expects(self::once())
            ->method('newWorkflowStub')
            ->willReturnCallback(function (string $class, ?WorkflowOptions $options = null) use ($stub, &$capturedOptions) {
                $capturedOptions = $options;
                return $stub;
            });

        $this->client->expects(self::once())
            ->method('start')
            ->with($stub, 42)
            ->willReturn($this->createMock(\Temporal\Workflow\WorkflowRunInterface::class));

        $handle = WorkflowHandle::fromWorkflowMethod(
            FakeWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(self::WID),
            42,
        );

        $info = WorkflowRunOperation::start(
            $handle,
            new OperationStartDetails(
                requestId: 'req-1',
                callbackUrl: 'https://callback.example/done',
                callbackHeaders: ['X-Caller' => 'demo'],
                links: [],
            ),
        );

        self::assertInstanceOf(OperationInfo::class, $info);
        self::assertSame(OperationState::Running, $info->state);

        $expectedToken = WorkflowRunOperationToken::generate(self::NS, self::WID);
        self::assertSame($expectedToken, $info->token);

        self::assertNotNull($capturedOptions);
        self::assertSame('req-1', $capturedOptions->requestId);
        self::assertCount(1, $capturedOptions->completionCallbacks);

        $callback = $capturedOptions->completionCallbacks[0];
        self::assertSame('https://callback.example/done', $callback->url);
        self::assertSame('demo', $callback->headers['X-Caller']);
        self::assertSame($expectedToken, $callback->headers['Nexus-Operation-Token']);
        self::assertSame($expectedToken, $callback->headers['nexus-operation-id']);
    }

    public function testStartWithoutCallbackOmitsCompletionCallback(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);

        $captured = null;
        $this->client->method('newWorkflowStub')->willReturnCallback(
            function (string $class, ?WorkflowOptions $options = null) use ($stub, &$captured) {
                $captured = $options;
                return $stub;
            },
        );
        $this->client->method('start')->willReturn($this->createMock(\Temporal\Workflow\WorkflowRunInterface::class));

        WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(self::WID),
            ),
            new OperationStartDetails(requestId: 'req-no-cb', callbackUrl: null, callbackHeaders: [], links: []),
        );

        self::assertSame([], $captured->completionCallbacks);
        self::assertSame('req-no-cb', $captured->requestId);
    }

    public function testStartUsesNexusContextTaskQueueByDefault(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);

        $captured = null;
        $this->client->method('newWorkflowStub')->willReturnCallback(
            function (string $class, ?WorkflowOptions $options = null) use ($stub, &$captured) {
                $captured = $options;
                return $stub;
            },
        );
        $this->client->method('start')->willReturn($this->createMock(\Temporal\Workflow\WorkflowRunInterface::class));

        WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(self::WID),
            ),
            new OperationStartDetails(requestId: 'req-test', callbackUrl: null, callbackHeaders: [], links: []),
        );

        self::assertSame('tq', $captured->taskQueue, 'task queue should fall back to Nexus context value');
    }

    public function testStartPreservesUserProvidedTaskQueue(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);

        $captured = null;
        $this->client->method('newWorkflowStub')->willReturnCallback(
            function (string $class, ?WorkflowOptions $options = null) use ($stub, &$captured) {
                $captured = $options;
                return $stub;
            },
        );
        $this->client->method('start')->willReturn($this->createMock(\Temporal\Workflow\WorkflowRunInterface::class));

        WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()
                    ->withWorkflowId(self::WID)
                    ->withTaskQueue('explicit-queue'),
            ),
            new OperationStartDetails(requestId: 'req-test', callbackUrl: null, callbackHeaders: [], links: []),
        );

        self::assertSame('explicit-queue', $captured->taskQueue);
    }

    public function testStartAddsSelfLinkToOperationContext(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);
        $this->client->method('newWorkflowStub')->willReturn($stub);

        $run = $this->createMock(\Temporal\Workflow\WorkflowRunInterface::class);
        $run->method('getExecution')->willReturn(
            new \Temporal\Workflow\WorkflowExecution(self::WID, 'run-xyz'),
        );
        $this->client->method('start')->willReturn($run);

        WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(self::WID),
            ),
            new OperationStartDetails(requestId: 'req-1', callbackUrl: null, callbackHeaders: [], links: []),
        );

        $links = Nexus::getCurrentContext()->links->all();
        self::assertCount(1, $links);
        self::assertSame(\Temporal\Internal\Nexus\NexusLinkConverter::TYPE_WORKFLOW_EVENT, $links[0]->type);
        self::assertStringContainsString('/namespaces/' . self::NS . '/', $links[0]->uri);
        self::assertStringContainsString('/workflows/' . self::WID . '/run-xyz/history', $links[0]->uri);
        self::assertStringContainsString('eventType=WorkflowExecutionStarted', $links[0]->uri);
    }

    public function testStartFailsWithoutWorkflowId(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('workflow ID is required');

        WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(''),
            ),
            new OperationStartDetails(requestId: 'req-test', callbackUrl: null, callbackHeaders: [], links: []),
        );
    }

    public function testCancelDecodesTokenAndCancelsWorkflow(): void
    {
        $token = WorkflowRunOperationToken::generate(self::NS, self::WID);
        $stub = $this->createMock(WorkflowStubInterface::class);
        $stub->expects(self::once())->method('cancel');

        $this->client->expects(self::once())
            ->method('newUntypedRunningWorkflowStub')
            ->with(self::WID)
            ->willReturn($stub);

        WorkflowRunOperation::cancel($token);
    }

    public function testCancelRejectsBadToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WorkflowRunOperation::cancel('not-a-real-token');
    }
}

/** @internal Local fixture — needed only as a class-string for WorkflowHandle. */
final class FakeWorkflow {}
