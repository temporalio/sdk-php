<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\AsyncCompletion;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\NexusOperationCancellationType;
use Temporal\Workflow\NexusOperationOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/** Caller cancels an in-flight async op; cancel propagation lands in caller history. */
#[Worker(options: [self::class, 'workerOptions'])]
class AsyncCompletionTest extends TestCase
{
    /** Handler workflow timer duration. Read by handler/caller workflows below. */
    public const HANDLER_DURATION_SECONDS = 10;

    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function asyncHandlerCancellationLandsInHistory(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-completion-cancel');

        $caller = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncCompletion_CancelCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(60)),
        );

        $client->start($caller, $endpoint->name, 'payload');

        self::assertSame('cancelled', $caller->getResult('string', timeout: 30));

        $cancelRequested = false;
        foreach ($client->getWorkflowHistory($caller->getExecution()) as $event) {
            if ($event->getEventType() === EventType::EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUESTED) {
                $cancelRequested = true;
                break;
            }
        }

        self::assertTrue(
            $cancelRequested,
            'Expected EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUESTED in caller workflow history; none found.',
        );
    }
}

// ── Nexus service ──────────────────────────────────────────────────────

#[Service(name: 'AsyncCompletionService')]
class AsyncCompletionService
{
    #[AsyncOperation(output: 'string')]
    public function longRunning(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            LongRunningHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(Nexus::getStartDetails()->requestId),
            $input,
        );
    }
}

// ── Handler workflow ────────────────────────────────────────────────────

#[WorkflowInterface]
class LongRunningHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncCompletion_Handler')]
    public function handle(string $input)
    {
        try {
            yield Workflow::timer(CarbonInterval::seconds(AsyncCompletionTest::HANDLER_DURATION_SECONDS));
            return "ok:{$input}";
        } catch (CanceledFailure) {
            return "cancelled:{$input}";
        }
    }
}

// ── Caller workflow: start, then cancel after one task boundary ─────────

#[WorkflowInterface]
class CancelCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncCompletion_CancelCaller')]
    public function run(string $endpoint, string $input)
    {
        $stub = Workflow::newNexusServiceStub(
            AsyncCompletionService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(
                    CarbonInterval::seconds(AsyncCompletionTest::HANDLER_DURATION_SECONDS + 30),
                )
                ->withCancellationType(NexusOperationCancellationType::WaitRequested),
        );

        $promise = null;
        $scope = Workflow::async(static function () use ($stub, $input, &$promise): void {
            $promise = $stub->longRunning($input);
        });

        // One task boundary so the schedule command is flushed before the cancel.
        yield Workflow::timer(CarbonInterval::seconds(NexusWorkerOptions::PRE_CANCEL_TIMER_SECONDS));
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
