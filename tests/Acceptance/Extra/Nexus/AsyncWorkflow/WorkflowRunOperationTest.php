<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncWorkflow;

use Carbon\CarbonInterval;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * End-to-end async Nexus operation:
 *   caller workflow → Nexus stub → WorkflowRunOperation → handler workflow → completion.
 *
 * Flow: PHP issues `ExecuteNexusOperation` and `GetNexusOperationStarted` in
 * the same task; RR's nexusStarted registry pushes the start envelope when
 * the SDK ack's the start, RR's completion callback resolves the
 * `ExecuteNexusOperation` response when the handler workflow finishes.
 * Tight 5s execution timeout — handler completes in ~50ms so passing runs
 * finish well under 5s.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class WorkflowRunOperationTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function asyncWorkflowOperationCompletesAndPropagatesResult(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-wf');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncWorkflow_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $client->start($stub, $endpoint->name, 'world');

        self::assertSame('HELLO, WORLD!', $stub->getResult('string'));
    }

    /**
     * Verifies the request-id → handler-workflow-id mapping that
     * {@see AsyncWorkflowService::hello()} sets up via
     * `WorkflowOptions::withWorkflowId($details->requestId)`.
     *
     * Strategy: the handler stashes the start-details requestId into a
     * file-backed marker (same trick as the interceptor cancel test, since
     * the handler may run in a different RR worker process than PHPUnit).
     * After the caller workflow completes, the test reads the marker and
     * asserts that a workflow execution with that exact ID exists in
     * Temporal — i.e. the requestId really did become the workflow ID.
     */
    #[Test]
    public function requestIdIsPropagatedToHandlerWorkflowId(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        RequestIdMarker::clear();

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-wf-rid');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncWorkflow_Caller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $client->start($stub, $endpoint->name, 'idempotent');

        // End-to-end completion still works (regression on top of the mapping check).
        self::assertSame('HELLO, IDEMPOTENT!', $stub->getResult('string'));

        $requestId = RequestIdMarker::read();
        self::assertNotNull($requestId, 'Handler never recorded the Nexus start requestId.');
        self::assertNotSame('', $requestId, 'Recorded Nexus requestId must be non-empty.');

        // The handler set its workflow-id to the requestId via
        // `WorkflowOptions::withWorkflowId($details->requestId)`. Asking the
        // client for the result of that workflow id proves a workflow with
        // exactly that id ran (and matches the caller's result, which is
        // what gets relayed through Nexus).
        $handlerStub = $client->newUntypedRunningWorkflowStub(
            $requestId,
            workflowType: 'Extra_Nexus_AsyncWorkflow_Handler',
        );
        self::assertSame('HELLO, IDEMPOTENT!', $handlerStub->getResult('string', timeout: 10));

        RequestIdMarker::clear();
    }

    /**
     * Cancelling the caller scope cancels the in-flight Nexus operation:
     * the cancel propagates through `WorkflowRunOperation::cancel()` to the
     * handler workflow, whose long timer is interrupted by a
     * {@see CanceledFailure}. The caller observes the operation rejecting and
     * resolves to `'cancelled'`.
     */
    #[Test]
    public function cancellingScopeCancelsNexusOperation(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-wf-cancel');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncWorkflow_CancelCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(60)),
        );

        $client->start($stub, $endpoint->name, 'world');

        self::assertSame('cancelled', $stub->getResult('string'));
    }
}

/**
 * File-backed marker for the request-id captured inside the Nexus handler.
 *
 * The handler runs in a RoadRunner worker process that is separate from the
 * PHPUnit process; a process-local static would not be visible to the test,
 * so we use the same filesystem hand-off pattern as
 * {@see \Temporal\Tests\Acceptance\Extra\Nexus\Interceptor\WorkerLocalMarker}.
 */
final class RequestIdMarker
{
    public const FILE = '/tmp/nexus-async-wf-request-id-marker';

    public static function record(string $requestId): void
    {
        \file_put_contents(self::FILE, $requestId);
    }

    public static function read(): ?string
    {
        if (!\is_file(self::FILE)) {
            return null;
        }
        $contents = \file_get_contents(self::FILE);
        return $contents === false ? null : $contents;
    }

    public static function clear(): void
    {
        if (\is_file(self::FILE)) {
            \unlink(self::FILE);
        }
    }
}

// ── Nexus service ──────────────────────────────────────────────────

#[Service(name: 'AsyncWorkflowService')]
class AsyncWorkflowService
{
    #[AsyncOperation(output: 'string')]
    public function hello(string $input): WorkflowHandle
    {
        $details = Nexus::getStartDetails();
        // Side-channel: stash the requestId so the PHPUnit process can later
        // assert that the handler workflow was started under exactly this id.
        // See RequestIdMarker for the cross-process rationale.
        RequestIdMarker::record($details->requestId);
        // Declarative style: return the handle, the SDK runs WorkflowRunOperation::start.
        return WorkflowHandle::fromWorkflowMethod(
            AsyncHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId($details->requestId),
            $input,
        );
    }

    #[OperationCancel(operation: 'hello')]
    public function cancelHello(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }

    #[AsyncOperation(output: 'string')]
    public function slowHello(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                SlowAsyncHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $input,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'slowHello')]
    public function cancelSlowHello(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

// ── Handler workflow ───────────────────────────────────────────────

#[WorkflowInterface]
class AsyncHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_Handler')]
    public function handle(string $input)
    {
        // Yield once so the workflow takes at least one task — exercises the
        // async-completion path rather than collapsing into a sync result.
        yield Workflow::timer(CarbonInterval::milliseconds(50));
        return 'HELLO, ' . \strtoupper($input) . '!';
    }
}

/**
 * Long-running handler so the caller has a window to cancel the operation
 * before it completes. The 45s timer is interrupted by a {@see CanceledFailure}
 * once the cancel propagates from the caller through Temporal.
 */
#[WorkflowInterface]
class SlowAsyncHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_SlowHandler')]
    public function handle(string $input)
    {
        try {
            yield Workflow::timer(CarbonInterval::seconds(45));
            return 'HELLO, ' . \strtoupper($input) . '!';
        } catch (CanceledFailure) {
            return 'cancelled:' . $input;
        }
    }
}

// ── Caller workflows ───────────────────────────────────────────────

#[WorkflowInterface]
class AsyncCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_Caller')]
    public function run(string $endpoint, string $input)
    {
        $stub = Workflow::newNexusServiceStub(
            AsyncWorkflowService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(30)),
        );
        return yield $stub->hello($input);
    }
}

#[WorkflowInterface]
class AsyncCancelCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_CancelCaller')]
    public function run(string $endpoint, string $input)
    {
        $stub = Workflow::newNexusServiceStub(
            AsyncWorkflowService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(60))
                ->withCancellationType(NexusOperationCancellationType::WaitRequested),
        );

        $operation = null;
        $scope = Workflow::async(static function () use ($stub, $input, &$operation): void {
            $operation = $stub->slowHello($input);
        });

        yield Workflow::timer(CarbonInterval::seconds(2));
        $scope->cancel();

        try {
            yield $operation;
            return 'unexpected-no-failure';
        } catch (NexusOperationFailure $e) {
            return $e->getPrevious() instanceof CanceledFailure
                ? 'cancelled'
                : 'unexpected-cause:' . ($e->getPrevious()?->getMessage() ?? 'null');
        } catch (CanceledFailure) {
            return 'cancelled';
        }
    }
}
