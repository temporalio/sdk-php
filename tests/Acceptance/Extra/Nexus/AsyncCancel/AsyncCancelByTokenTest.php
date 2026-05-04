<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncCancel;

use Carbon\CarbonInterval;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Nexus\WorkflowRunOperation;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Caller cancels an in-flight async Nexus operation; the rejection surfaces
 * as a {@see NexusOperationFailure} with a {@see CanceledFailure} cause.
 *
 * Cancel propagates through the standard promise chain:
 * cancel on the result promise → cancels the underlying `ExecuteNexusOperation`
 * request via {@see RequestCancelNexusOperation}. The completion callback
 * eventually fires with a CanceledFailure, the result promise rejects, and
 * the caller catches `NexusOperationFailure` and returns `'cancelled'`.
 *
 * Exercises:
 *   - {@see NexusOperationCancellationType::WaitRequested} — caller resumes
 *     once the handler ack'd the cancel.
 *   - {@see WorkflowRunOperation::cancel()} — decodes the workflow-run token
 *     and cancels the underlying handler workflow run.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class AsyncCancelByTokenTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function cancelSurfacesAsCanceledFailureCause(
        State $state,
        WorkflowClientInterface $client,
    ): void {
        $endpoint = NexusHelper::for($state)->setupEndpointWithName(
            $state->namespace,
            __NAMESPACE__,
            'nexus-async-cancel',
        );

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncWorkflow_CancelCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $client->start($stub, $endpoint['name']);

        self::assertSame('cancelled', $stub->getResult('string'));
    }
}

// ── Nexus service ──────────────────────────────────────────────────

#[Service(name: 'AsyncCancelService')]
interface AsyncCancelService
{
    #[AsyncOperation(output: 'string')]
    public function longRunning(string $input): OperationInfo;
}

class AsyncCancelServiceImpl implements AsyncCancelService
{
    public function longRunning(string $input): OperationInfo
    {
        $details = Nexus::getStartDetails();
        return WorkflowRunOperation::start(
            WorkflowHandle::fromWorkflowMethod(
                LongRunningHandlerWorkflow::class,
                WorkflowOptions::new()->withWorkflowId($details->requestId),
                $input,
            ),
            $details,
        );
    }

    #[OperationCancel(operation: 'longRunning')]
    public function cancelLongRunning(string $token): void
    {
        WorkflowRunOperation::cancel($token);
    }
}

// ── Handler workflow: sleeps long enough that the caller can cancel ──

#[WorkflowInterface]
class LongRunningHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_LongHandler')]
    public function handle(string $input)
    {
        try {
            yield Workflow::timer(CarbonInterval::seconds(30));
            return "completed:{$input}";
        } catch (CanceledFailure) {
            return "cancelled:{$input}";
        }
    }
}

// ── Caller workflow: starts, then cancels via async scope ──────────

#[WorkflowInterface]
class CancelCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncWorkflow_CancelCaller')]
    public function run(string $endpoint)
    {
        $stub = Workflow::newNexusServiceStub(
            AsyncCancelService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(60))
                ->withCancellationType(NexusOperationCancellationType::WaitRequested),
        );

        $promise = null;
        $scope = Workflow::async(static function () use ($stub, &$promise): void {
            $promise = $stub->longRunning('payload');
        });

        // Give the handler workflow a chance to actually start before cancelling.
        yield Workflow::timer(CarbonInterval::seconds(1));
        $scope->cancel();

        try {
            yield $promise;
        } catch (NexusOperationFailure $e) {
            if ($e->getPrevious() instanceof CanceledFailure) {
                return 'cancelled';
            }
            throw $e;
        }

        return 'unexpected-completion';
    }
}
