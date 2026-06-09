<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Replay;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\NexusOperationFailure;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\WorkflowHandle;
use Temporal\Testing\Replay\Exception\ReplayerException;
use Temporal\Testing\Replay\WorkflowReplayer;
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
 * Replay coverage for caller-side Nexus operations.
 *
 * The PHP workflow code is identical between live execution and replay:
 * the same `NewTimer` / `ExecuteNexusOperation` / `GetNexusOperationStarted`
 * requests are pushed in the same order, and RR's replay path resolves them
 * from history instead of going to the server. A successful replay proves
 * the workflow is deterministic across both flows.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class ReplayTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function asyncWorkflowRunOperationReplaysCleanly(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-replay-async');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Replay_AsyncCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(120)),
        );

        $client->start($stub, $endpoint->name, 'world');
        self::assertSame('HELLO, WORLD!', $stub->getResult('string'));

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();

        // Started is the discriminator that matters for replay: async ops emit
        // both Scheduled and Started, sync ops do not. If Started is missing
        // we are silently testing the sync path and the test name lies.
        self::assertContainsEvents(
            $history,
            [
                EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED,
                EventType::EVENT_TYPE_NEXUS_OPERATION_STARTED,
                EventType::EVENT_TYPE_NEXUS_OPERATION_COMPLETED,
            ],
            'async caller history must include Scheduled+Started+Completed Nexus events',
        );

        (new WorkflowReplayer())->replayHistory($history);
    }

    #[Test]
    public function timerBeforeNexusOperationReplaysCleanly(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-replay-timer');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Replay_TimerThenSyncCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(120)),
        );

        $client->start($stub, $endpoint->name, 'world');
        self::assertSame('Hello, world!', $stub->getResult('string'));

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();

        // Timer + Nexus together exercise Request::$lastID ordering: both
        // claim sequential 9000+ ids, both must come back in lockstep on
        // replay or we get a nondeterminism error on the second yield.
        self::assertContainsEvents(
            $history,
            [
                EventType::EVENT_TYPE_TIMER_STARTED,
                EventType::EVENT_TYPE_TIMER_FIRED,
                EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED,
                EventType::EVENT_TYPE_NEXUS_OPERATION_COMPLETED,
            ],
            'timer-then-nexus history must include both timer and Nexus events',
        );

        (new WorkflowReplayer())->replayHistory($history);
    }

    #[Test]
    public function syncNexusOperationReplaysCleanly(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-replay-sync');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Replay_SyncCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(120)),
        );

        $client->start($stub, $endpoint->name, 'world');
        self::assertSame('Hello, world!', $stub->getResult('string'));

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();

        self::assertContainsEvents(
            $history,
            [
                EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED,
                EventType::EVENT_TYPE_NEXUS_OPERATION_COMPLETED,
            ],
            'sync caller history must include Scheduled+Completed Nexus events',
        );

        // Real assertion: replay runs the workflow against the recorded history;
        // any nondeterminism (different command order, missed yield, drift in
        // payload encoding) raises NonDeterministicWorkflowException.
        (new WorkflowReplayer())->replayHistory($history);
    }

    #[Test]
    public function syncNexusOperationReplaysFromDumpedJsonFile(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-replay-dump');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Replay_SyncCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(120)),
        );

        $client->start($stub, $endpoint->name, 'world');
        self::assertSame('Hello, world!', $stub->getResult('string'));

        $file = \dirname(__DIR__, 4) . '/runtime/tests/nexus-sync-history.json';
        \is_dir(\dirname($file)) or \mkdir(\dirname($file), recursive: true);
        \is_file($file) and \unlink($file);

        try {
            $replayer = new WorkflowReplayer();
            $replayer->downloadHistory(
                'Extra_Nexus_Replay_SyncCaller',
                $stub->getExecution(),
                $file,
            );
            self::assertFileExists($file);
            // Belt-and-suspenders: the file must really contain Nexus events
            // before the JSON-replay step gets credit for replaying them.
            $contents = (string) \file_get_contents($file);
            self::assertStringContainsString(
                'EVENT_TYPE_NEXUS_OPERATION_SCHEDULED',
                $contents,
                'Dumped history JSON must contain a Nexus scheduled event.',
            );
            self::assertStringContainsString(
                'EVENT_TYPE_NEXUS_OPERATION_COMPLETED',
                $contents,
                'Dumped history JSON must contain a Nexus completed event.',
            );

            // Round-trip: parse the file back through RR's replayer; same
            // determinism contract as replayHistory(), but exercises the
            // JSON deserialiser path independent of the proto-from-server one.
            $replayer->replayFromJSON('Extra_Nexus_Replay_SyncCaller', $file);
        } finally {
            \is_file($file) and \unlink($file);
        }
    }

    #[Test]
    public function mutatedNexusScheduledEventIsRejectedByReplayer(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-replay-mutate');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Replay_SyncCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(120)),
        );

        $client->start($stub, $endpoint->name, 'world');
        self::assertSame('Hello, world!', $stub->getResult('string'));

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();

        // Pre-condition: a clean replay must succeed first, otherwise we
        // have no baseline and the negative assertion below is vacuous.
        (new WorkflowReplayer())->replayHistory($history);

        // Validation core: rewrite the operation name on the recorded
        // SCHEDULED event. PHP, on replay, will yield
        // `Workflow::newNexusServiceStub(...)->greet(...)` and emit a
        // ScheduleNexusOperationCommand for `operation='greet'`. The
        // replayer must compare that command against the (now-mutated)
        // history and detect a mismatch — proving it actually inspects
        // Nexus events instead of skipping them.
        $mutated = false;
        foreach ($history->getEvents() as $event) {
            if ($event->getEventType() !== EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED) {
                continue;
            }
            $attrs = $event->getNexusOperationScheduledEventAttributes();
            self::assertNotNull($attrs);
            self::assertSame('greet', $attrs->getOperation());
            $attrs->setOperation('mutatedOperationName');
            $mutated = true;
            break;
        }
        self::assertTrue(
            $mutated,
            'Test setup expected at least one NEXUS_OPERATION_SCHEDULED in history.',
        );

        $this->expectException(ReplayerException::class);
        (new WorkflowReplayer())->replayHistory($history);
    }

    #[Test]
    public function cancelledNexusOperationReplaysCleanly(
        State $state,
        WorkflowClientInterface $client,
        NexusEndpoints $endpoints,
    ): void {
        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'nexus-replay-cancel');

        $stub = $client->newUntypedWorkflowStub(
            'Extra_Nexus_Replay_CancelCaller',
            WorkflowOptions::new()
                ->withTaskQueue(__NAMESPACE__)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(120)),
        );

        $client->start($stub, $endpoint->name, 'world');
        self::assertSame('cancelled', $stub->getResult('string'));

        $history = $client->getWorkflowHistory($stub->getExecution())->getHistory();

        // The cancel path must leave both the request marker and the terminal
        // CANCELED event in history; replay then re-derives the same command
        // stream (schedule + cancel) from these events.
        self::assertContainsEvents(
            $history,
            [
                EventType::EVENT_TYPE_NEXUS_OPERATION_SCHEDULED,
                EventType::EVENT_TYPE_NEXUS_OPERATION_CANCEL_REQUESTED,
                EventType::EVENT_TYPE_NEXUS_OPERATION_CANCELED,
            ],
            'cancelled caller history must include Scheduled+CancelRequested+Canceled Nexus events',
        );

        (new WorkflowReplayer())->replayHistory($history);
    }

    /**
     * @param list<int> $expectedTypes
     */
    private static function assertContainsEvents(
        \Temporal\Api\History\V1\History $history,
        array $expectedTypes,
        string $message,
    ): void {
        $present = [];
        foreach ($history->getEvents() as $event) {
            $present[$event->getEventType()] = true;
        }
        foreach ($expectedTypes as $type) {
            self::assertArrayHasKey(
                $type,
                $present,
                $message . ' — missing event type ' . EventType::name($type),
            );
        }
    }
}

// ── Sync Nexus service ─────────────────────────────────────────────────

#[Service(name: 'ReplaySyncService')]
class ReplaySyncService
{
    #[Operation]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}

// ── Async Nexus service (WorkflowRunOperation) ─────────────────────────

#[Service(name: 'ReplayAsyncService')]
class ReplayAsyncService
{
    #[AsyncOperation(output: 'string')]
    public function shout(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            ReplayAsyncHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(Nexus::getStartDetails()->requestId),
            $input,
        );
    }
}

// ── Async handler workflow ─────────────────────────────────────────────

#[WorkflowInterface]
class ReplayAsyncHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Replay_AsyncHandler')]
    public function handle(string $input)
    {
        // Force at least one task transition so the operation goes async
        // (Started event), matching the production WorkflowRunOperation path.
        yield Workflow::timer(CarbonInterval::milliseconds(50));
        return 'HELLO, ' . \strtoupper($input) . '!';
    }
}

// ── Caller workflows ───────────────────────────────────────────────────

#[WorkflowInterface]
class ReplaySyncCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Replay_SyncCaller')]
    public function run(string $endpoint, string $name)
    {
        $stub = Workflow::newNexusServiceStub(
            ReplaySyncService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20)),
        );
        return yield $stub->greet($name);
    }
}

#[WorkflowInterface]
class ReplayAsyncCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Replay_AsyncCaller')]
    public function run(string $endpoint, string $input)
    {
        $stub = Workflow::newNexusServiceStub(
            ReplayAsyncService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20)),
        );
        return yield $stub->shout($input);
    }
}

#[WorkflowInterface]
class ReplayTimerThenSyncCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Replay_TimerThenSyncCaller')]
    public function run(string $endpoint, string $name)
    {
        // Timer first — separate workflow task before the Nexus call —
        // is the minimal interleaving that catches per-task command-id drift.
        yield Workflow::timer(CarbonInterval::milliseconds(50));

        $stub = Workflow::newNexusServiceStub(
            ReplaySyncService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(20)),
        );
        return yield $stub->greet($name);
    }
}

// ── Cancel-path Nexus service (long handler that does not recover) ──────

#[Service(name: 'ReplayCancelService')]
class ReplayCancelService
{
    #[AsyncOperation(output: 'string')]
    public function longRunning(string $input): WorkflowHandle
    {
        return WorkflowHandle::fromWorkflowMethod(
            ReplayCancelHandlerWorkflow::class,
            WorkflowOptions::new()->withWorkflowId(Nexus::getStartDetails()->requestId),
            $input,
        );
    }
}

#[WorkflowInterface]
class ReplayCancelHandlerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Replay_CancelHandler')]
    public function handle(string $input)
    {
        yield Workflow::timer(CarbonInterval::seconds(30));
        return "completed:{$input}";
    }
}

#[WorkflowInterface]
class ReplayCancelCallerWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Replay_CancelCaller')]
    public function run(string $endpoint, string $input)
    {
        // WaitCompleted (not WaitRequested): the caller future must resolve on the
        // terminal NEXUS_OPERATION_CANCELED event, not on CANCEL_REQUEST_COMPLETED.
        // WaitRequested unblocks at the cancel-ack and the caller workflow closes its
        // history *before* the server writes NEXUS_OPERATION_CANCELED, so that event
        // never lands in the caller history and the assertion below can't see it.
        $stub = Workflow::newNexusServiceStub(
            ReplayCancelService::class,
            NexusOperationOptions::new()
                ->withEndpoint($endpoint)
                ->withScheduleToCloseTimeout(CarbonInterval::seconds(60))
                ->withCancellationType(NexusOperationCancellationType::WaitCompleted),
        );

        $promise = null;
        $scope = Workflow::async(static function () use ($stub, $input, &$promise): void {
            $promise = $stub->longRunning($input);
        });

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
