<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Timeout;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Exception\Failure\TimeoutFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * P2 #10–11 — `scheduleToCloseTimeout` enforcement on caller side.
 *
 * Two scenarios:
 *   - sync handler runs longer than the timeout → caller catches a
 *     {@see NexusOperationFailure} whose cause is {@see TimeoutFailure}.
 *   - async handler workflow runs longer than the timeout → same.
 *
 * P2 #12 ("retry within timeout") is deferred — `NexusOperationOptions` does
 * not currently expose a retry policy, so retry semantics are server-driven
 * and not under direct caller control. Tracked as a gap in nexus_plan.md.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class TimeoutTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function syncOperationTimesOutOnCaller(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-timeout-sync',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Timeout_SyncCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        $client->start($stub, $endpoint['name']);

        self::assertSame('ok', $stub->getResult('string'));
    }

    #[Test]
    public function asyncOperationTimesOutOnCaller(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-timeout-async',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Timeout_AsyncCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(20)),
        );

        $client->start($stub, $endpoint['name']);

        self::assertSame('ok', $stub->getResult('string'));
    }
}

// ── Sync service: handler sleeps past the caller's timeout ─────────

#[Service(name: 'TimeoutSyncService')]
interface TimeoutSyncService
{
    #[Operation]
    public function slowSync(string $input): string;
}

class TimeoutSyncServiceImpl implements TimeoutSyncService
{
    public function slowSync(string $input): string
    {
        // PHP-side handler blocks the worker thread; sleep just past the
        // caller's 2s scheduleToCloseTimeout.
        \sleep(5);
        return "should-not-reach:{$input}";
    }
}

#[WorkflowInterface]
class TimeoutSyncCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Timeout_SyncCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            TimeoutSyncService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(2)),
        );

        try {
            yield $stub->slowSync('payload');
        } catch (NexusOperationFailure $e) {
            $cause = $e->getPrevious();
            if (!$cause instanceof TimeoutFailure) {
                $causeName = $cause === null ? 'null' : $cause::class;
                return "wrong-cause:{$causeName}";
            }
            return 'ok';
        }

        return 'unexpected:no-exception';
    }
}

// ── Async service: handler workflow sleeps past the caller's timeout ─

#[Service(name: 'TimeoutAsyncService')]
interface TimeoutAsyncService
{
    #[AsyncOperation(output: 'string')]
    public function slowAsync(string $input): OperationInfo;
}

class TimeoutAsyncServiceImpl implements TimeoutAsyncService
{
    public function slowAsync(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                SlowHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $input,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'slowAsync')]
    public function cancel(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

#[WorkflowInterface]
class SlowHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Timeout_SlowHandler')]
    public function handle(string $input)
    {
        // Sleep well past the caller's 3s timeout.
        yield Workflow::timer(CarbonInterval::seconds(15));
        return "should-not-reach:{$input}";
    }
}

#[WorkflowInterface]
class TimeoutAsyncCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Timeout_AsyncCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            TimeoutAsyncService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(3)),
        );

        try {
            yield $stub->slowAsync('payload');
        } catch (NexusOperationFailure $e) {
            $cause = $e->getPrevious();
            if (!$cause instanceof TimeoutFailure) {
                $causeName = $cause === null ? 'null' : $cause::class;
                return "wrong-cause:{$causeName}";
            }
            return 'ok';
        }

        return 'unexpected:no-exception';
    }
}
