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
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
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
 * Acceptance tests for the async-handler completion path.
 *
 * Both tests share the same Nexus service (10-second handler workflow) and
 * differ only in the caller's behavior:
 *
 *   1. {@see self::asyncHandlerCompletesAndDeliversCallback()} — caller waits
 *      for natural completion. Temporal's completion callback fires when the
 *      handler workflow returns; the caller observes the operation result.
 *
 *   2. {@see self::asyncHandlerCancellationLandsInHistory()} — caller cancels
 *      the operation right after starting it. After the handler-duration
 *      window passes, the test fetches the caller's workflow history and
 *      asserts that a Nexus cancel-side event is recorded.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class AsyncCompletionTest extends TestCase
{
    /** Handler workflow timer duration. Read by handler/caller workflows below. */
    public const HANDLER_DURATION_SECONDS = 10;

    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function asyncHandlerCompletesAndDeliversCallback(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-async-completion-ok');

        $caller = $client->newUntypedWorkflowStub(
            'Extra_Nexus_AsyncCompletion_WaitCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(60)),
        );

        $client->start($caller, $endpoint->name, 'payload');

        // Caller blocks on the operation result until the handler workflow's
        // 10-second timer fires and the completion callback resolves the
        // pending ExecuteNexusOperation request. Generous client-side timeout
        // accounts for scheduling overhead on slow CI hosts.
        $result = $caller->getResult('string', timeout: 30);
        self::assertSame('ok:payload', $result);
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

        // Caller starts the op then cancels on the next instruction; with
        // WaitRequested it resumes once the handler ack'd the cancel. We
        // assert the caller saw the cancel propagate (via NexusOperationFailure
        // -> CanceledFailure) and returned 'cancelled'.
        self::assertSame('cancelled', $caller->getResult('string', timeout: 30));

        // Give Temporal a beat to flush the cancel-side event into history,
        // then iterate. Any cancel-side Nexus event proves the cancel reached
        // the workflow's history — Temporal records both
        // EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUESTED (caller side) and
        // EVENT_TYPE_NEXUS_OPERATION_CANCELED (terminal) for cancelled ops.
        $cancelEventTypes = [
            EventType::EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUESTED,
            EventType::EVENT_TYPE_NEXUS_OPERATION_CANCELED,
        ];

        $found = [];
        foreach ($client->getWorkflowHistory($caller->getExecution()) as $event) {
            if (\in_array($event->getEventType(), $cancelEventTypes, true)) {
                $found[] = $event->getEventType();
            }
        }

        self::assertNotEmpty(
            $found,
            'Expected a Nexus cancel-side event in caller workflow history; none found.',
        );
    }
}

// ── Nexus service ──────────────────────────────────────────────────────

#[Service(name: 'AsyncCompletionService')]
class AsyncCompletionService
{
    #[AsyncOperation(output: 'string')]
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

// ── Handler workflow: sleeps 10s then completes ─────────────────────────

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

// ── Caller workflows ────────────────────────────────────────────────────

/**
 * Plain wait — exercises the success path: caller yields the op promise,
 * receives the handler's result via the completion callback.
 */
#[WorkflowInterface]
class WaitCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_AsyncCompletion_WaitCaller')]
    public function run(string $endpoint, string $input)
    {
        $stub = Workflow::newNexusServiceStub(
            AsyncCompletionService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(
                    CarbonInterval::seconds(AsyncCompletionTest::HANDLER_DURATION_SECONDS + 30),
                ),
        );
        return yield $stub->longRunning($input);
    }
}

/**
 * Start, then cancel on the very next instruction — exercises the cancel
 * path. The handler timer stays alive long enough that the cancel races in
 * before natural completion.
 */
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

        // Yield once so Temporal actually schedules the Nexus operation before
        // we cancel it — without a workflow task boundary the start command is
        // still buffered locally, the handler workflow never gets registered,
        // and the cancel has nothing to attach to (caller workflow then hangs
        // until execution timeout).
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
