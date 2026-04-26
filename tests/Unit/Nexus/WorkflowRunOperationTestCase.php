<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Nexus\Sdk\Handler\AsyncOperationStartResult;
use Nexus\Sdk\Handler\OperationCancelDetails;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationStartDetails;
use PHPUnit\Framework\MockObject\MockObject;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Internal\Nexus\WorkflowRunOperationToken;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\NexusOperationContext;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * Mocks the WorkflowClient and exercises the {@see WorkflowRunOperation}
 * factory end-to-end (sans the actual Temporal server).
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
        Nexus::setCurrent(new NexusOperationContext(self::NS, 'tq', $this->client));
    }

    protected function tearDown(): void
    {
        Nexus::setCurrent(null);
    }

    public function testStartReturnsAsyncTokenAndStartsWorkflow(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);

        // Capture the options passed to newWorkflowStub so we can assert that
        // requestId + callback got plumbed through correctly.
        $capturedOptions = null;
        $this->client->expects(self::once())
            ->method('newWorkflowStub')
            ->willReturnCallback(function (string $cls, ?WorkflowOptions $opts = null) use ($stub, &$capturedOptions) {
                $capturedOptions = $opts;
                return $stub;
            });

        $this->client->expects(self::once())
            ->method('start')
            ->with($stub, 42)
            ->willReturn($this->createMock(\Temporal\Workflow\WorkflowRunInterface::class));

        $handler = WorkflowRunOperation::fromWorkflowMethod(
            fn(): WorkflowHandle => WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(self::WID),
                42,
            ),
        );

        $result = $handler->start(
            new OperationContext(service: 'svc', operation: 'op'),
            new OperationStartDetails(
                requestId: 'req-1',
                callbackUrl: 'https://callback.example/done',
                callbackHeaders: ['X-Caller' => 'demo'],
                links: [],
            ),
            null,
        );

        self::assertInstanceOf(AsyncOperationStartResult::class, $result);

        $expectedToken = WorkflowRunOperationToken::generate(self::NS, self::WID);
        self::assertSame($expectedToken, $result->info->token);

        self::assertNotNull($capturedOptions);
        self::assertSame('req-1', $capturedOptions->requestId);
        self::assertCount(1, $capturedOptions->completionCallbacks);

        $callback = $capturedOptions->completionCallbacks[0]->getNexus();
        self::assertSame('https://callback.example/done', $callback->getUrl());

        $headers = [];
        foreach ($callback->getHeader() as $k => $v) {
            $headers[(string) $k] = (string) $v;
        }
        self::assertSame('demo', $headers['X-Caller']);
        self::assertSame($expectedToken, $headers['Nexus-Operation-Token']);
        self::assertSame($expectedToken, $headers['nexus-operation-id']);
    }

    public function testStartWithoutCallbackOmitsCompletionCallback(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);

        $captured = null;
        $this->client->method('newWorkflowStub')->willReturnCallback(
            function (string $cls, ?WorkflowOptions $opts = null) use ($stub, &$captured) {
                $captured = $opts;
                return $stub;
            },
        );
        $this->client->method('start')->willReturn($this->createMock(\Temporal\Workflow\WorkflowRunInterface::class));

        $handler = WorkflowRunOperation::fromWorkflowMethod(
            fn(): WorkflowHandle => WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(self::WID),
            ),
        );

        $handler->start(
            new OperationContext(service: 'svc', operation: 'op'),
            new OperationStartDetails(requestId: 'req-no-cb', callbackUrl: null, callbackHeaders: [], links: []),
            null,
        );

        self::assertSame([], $captured->completionCallbacks);
        self::assertSame('req-no-cb', $captured->requestId);
    }

    public function testStartUsesNexusContextTaskQueueByDefault(): void
    {
        // The factory leaves taskQueue at the WorkflowOptions default — Java's
        // WorkflowRunOperation backfills it with the worker's task queue
        // (the one this Nexus operation was dispatched on) so the workflow
        // lands on a queue that actually has a worker. Without this fix
        // workflows would silently park on `default` and never run.
        $stub = $this->createMock(WorkflowStubInterface::class);

        $captured = null;
        $this->client->method('newWorkflowStub')->willReturnCallback(
            function (string $cls, ?WorkflowOptions $opts = null) use ($stub, &$captured) {
                $captured = $opts;
                return $stub;
            },
        );
        $this->client->method('start')->willReturn($this->createMock(\Temporal\Workflow\WorkflowRunInterface::class));

        $handler = WorkflowRunOperation::fromWorkflowMethod(
            fn(): WorkflowHandle => WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(self::WID),
            ),
        );

        $handler->start(
            new OperationContext(service: 'svc', operation: 'op'),
            new OperationStartDetails(requestId: 'req-test', callbackUrl: null, callbackHeaders: [], links: []),
            null,
        );

        self::assertSame('tq', $captured->taskQueue, 'task queue should fall back to Nexus context value');
    }

    public function testStartPreservesUserProvidedTaskQueue(): void
    {
        // If the factory sets a task queue explicitly, the auto-fallback must
        // not overwrite it. This is the escape hatch when a handler wants to
        // park its workflow on a different worker pool.
        $stub = $this->createMock(WorkflowStubInterface::class);

        $captured = null;
        $this->client->method('newWorkflowStub')->willReturnCallback(
            function (string $cls, ?WorkflowOptions $opts = null) use ($stub, &$captured) {
                $captured = $opts;
                return $stub;
            },
        );
        $this->client->method('start')->willReturn($this->createMock(\Temporal\Workflow\WorkflowRunInterface::class));

        $handler = WorkflowRunOperation::fromWorkflowMethod(
            fn(): WorkflowHandle => WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()
                    ->withWorkflowId(self::WID)
                    ->withTaskQueue('explicit-queue'),
            ),
        );

        $handler->start(
            new OperationContext(service: 'svc', operation: 'op'),
            new OperationStartDetails(requestId: 'req-test', callbackUrl: null, callbackHeaders: [], links: []),
            null,
        );

        self::assertSame('explicit-queue', $captured->taskQueue);
    }

    public function testStartFailsWithoutWorkflowId(): void
    {
        // WorkflowOptions defaults to a fresh UUID, so we have to clear it
        // explicitly to exercise the guard. Real callers would forget to
        // call withWorkflowId() and end up with the UUID — also valid; this
        // covers the explicit-empty edge case.
        $handler = WorkflowRunOperation::fromWorkflowMethod(
            fn(): WorkflowHandle => WorkflowHandle::fromWorkflowMethod(
                FakeWorkflow::class,
                WorkflowOptions::new()->withWorkflowId(''),
            ),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('workflow ID is required');

        $handler->start(
            new OperationContext(service: 'svc', operation: 'op'),
            new OperationStartDetails(requestId: 'req-test', callbackUrl: null, callbackHeaders: [], links: []),
            null,
        );
    }

    public function testStartRejectsNonHandleFactoryReturn(): void
    {
        $handler = WorkflowRunOperation::fromWorkflowMethod(
            fn() => 'not a handle',
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('factory must return a');

        $handler->start(
            new OperationContext(service: 'svc', operation: 'op'),
            new OperationStartDetails(requestId: 'req-test', callbackUrl: null, callbackHeaders: [], links: []),
            null,
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

        $handler = WorkflowRunOperation::fromWorkflowMethod(
            fn(): WorkflowHandle => self::fail('factory should not run on cancel'),
        );

        $handler->cancel(
            new OperationContext(service: 'svc', operation: 'op'),
            new OperationCancelDetails(operationToken: $token),
        );
    }

    public function testCancelRejectsBadToken(): void
    {
        $handler = WorkflowRunOperation::fromWorkflowMethod(
            fn(): WorkflowHandle => self::fail('factory should not run on cancel'),
        );

        $this->expectException(\InvalidArgumentException::class);

        $handler->cancel(
            new OperationContext(service: 'svc', operation: 'op'),
            new OperationCancelDetails(operationToken: 'not-a-real-token'),
        );
    }

}

/** @internal Local fixture — needed only as a class-string for WorkflowHandle. */
final class FakeWorkflow {}
