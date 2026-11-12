<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use React\Promise\Deferred;
use Temporal\DataConverter\EncodedValues;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Promise;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Tests\Unit\Framework\CommandBatchMock;
use Temporal\Tests\Unit\Framework\Requests\DestroyWorkflow;
use Temporal\Tests\Unit\Framework\Requests\InvokeSignal;
use Temporal\Tests\Unit\Framework\Requests\StartWorkflow;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\Transport\Command\Client\SuccessClientResponse;
use Temporal\Worker\Transport\Command\Server\SuccessResponse;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

final class EvictionBatchTestCase extends AbstractUnit
{
    private WorkerFactoryMock $factory;

    public function testEvictionInItsOwnBatchKeepsTheNextBatchIntact(): void
    {
        $commands = $this->dispatch([new StartWorkflow('run-mutex', 'MutexHandoffWorkflow'), new InvokeSignal('run-mutex', 'handoff')]);
        $this->dispatch(self::fireTimers($commands));
        $this->dispatch([new DestroyWorkflow('run-mutex')]);

        $commands = $this->dispatch([new StartWorkflow('run-next', 'SingleTimerWorkflow')]);

        self::assertSame([NewTimer::class, SuccessClientResponse::class], \array_map(static fn(object $command): string => $command::class, $commands));
    }

    public function testEvictionDoesNotRunTheAwaitedCanceller(): void
    {
        $this->dispatch([new StartWorkflow('run-canceller', 'AwaitedCancellerWorkflow'), new DestroyWorkflow('run-canceller')]);

        self::assertSame([], AwaitedCancellerWorkflow::$canceller);
    }

    public function testEvictionDoesNotRunCancellersOfHeldCombinatorInputs(): void
    {
        $this->dispatch([new StartWorkflow('run-held', 'HeldCancellerWorkflow'), new DestroyWorkflow('run-held')]);

        self::assertSame([], HeldCancellerWorkflow::$canceller);
    }

    public function testEvictionTearsDownAfterTheWorkflowCodeOfItsBatch(): void
    {
        $commands = $this->dispatch([new StartWorkflow('run-resumed', 'BatchResumeWorkflow')]);
        $this->dispatch([...self::fireTimers($commands), new DestroyWorkflow('run-resumed')]);

        self::assertSame(['after', 'resumed', 'waiter', 'main'], BatchResumeWorkflow::$events);
    }

    public function testEvictionSendsNoCommandsFromFinallyBlocks(): void
    {
        $commands = $this->dispatch([new StartWorkflow('run-finally', 'TimerInFinallyWorkflow'), new DestroyWorkflow('run-finally')]);

        self::assertSame([NewTimer::class, SuccessClientResponse::class, SuccessClientResponse::class], \array_map(static fn(object $command): string => $command::class, $commands));
    }

    protected function setUp(): void
    {
        AwaitedCancellerWorkflow::$canceller = [];
        HeldCancellerWorkflow::$canceller = [];
        BatchResumeWorkflow::$events = [];
        $this->factory = WorkerFactoryMock::create();
        $worker = $this->factory->newWorker();
        $worker->registerWorkflowObject(new MutexHandoffWorkflow());
        $worker->registerWorkflowObject(new SingleTimerWorkflow());
        $worker->registerWorkflowObject(new AwaitedCancellerWorkflow());
        $worker->registerWorkflowObject(new TimerInFinallyWorkflow());
        $worker->registerWorkflowObject(new HeldCancellerWorkflow());
        $worker->registerWorkflowObject(new BatchResumeWorkflow());

        parent::setUp();
    }

    protected function tearDown(): void
    {
        Workflow::setCurrentContext(null);

        parent::tearDown();
    }

    private static function fireTimers(array $commands): array
    {
        $responses = [];
        foreach ($commands as $command) {
            if ($command instanceof NewTimer) {
                $responses[] = new SuccessResponse(EncodedValues::empty(), $command->getID(), new TickInfo(new \DateTimeImmutable()));
            }
        }

        return $responses;
    }

    private function dispatch(array $commands): array
    {
        $batch = new CommandBatchMock($commands, ['taskQueue' => 'default', 'tickTime' => (new \DateTimeImmutable())->format(\DATE_ATOM)]);
        $queue = (fn(CommandBatchMock $batch): iterable => $this->dispatch($batch))->call($this->factory, $batch);

        $commands = [];
        foreach ($queue as $command) {
            $commands[] = $command;
        }

        return $commands;
    }
}

#[Workflow\WorkflowInterface]
final class MutexHandoffWorkflow
{
    private Workflow\Mutex $mutex;

    #[WorkflowMethod(name: 'MutexHandoffWorkflow')]
    public function handler(): iterable
    {
        $this->mutex = new Workflow\Mutex();
        yield $this->mutex->lock();
        yield Workflow::timer(1);
        Workflow::timer(50);
        $this->mutex->unlock();
        yield $this->mutex->lock();
        $this->mutex->unlock();
        yield Workflow::timer(100);
    }

    #[Workflow\SignalMethod(name: 'handoff')]
    public function handoff(): iterable
    {
        yield $this->mutex->lock();
        $this->mutex->unlock();
        yield $this->mutex->lock();
        $this->mutex->unlock();
    }
}

#[Workflow\WorkflowInterface]
final class SingleTimerWorkflow
{
    #[WorkflowMethod(name: 'SingleTimerWorkflow')]
    public function handler(): iterable
    {
        yield Workflow::timer(3);
    }
}

#[Workflow\WorkflowInterface]
final class AwaitedCancellerWorkflow
{
    public static array $canceller = [];

    #[WorkflowMethod(name: 'AwaitedCancellerWorkflow')]
    public function handler(): iterable
    {
        $deferred = new Deferred(static function (): void {
            self::$canceller[] = Workflow::getInfo()->execution->getRunID();
            Workflow::timer(5)->then(static function (): void {
                self::$canceller[] = 'fulfilled';
            });
            self::$canceller[] = 'pending';
        });

        yield Promise::all([$deferred->promise(), Workflow::timer(100)]);
    }
}

#[Workflow\WorkflowInterface]
final class TimerInFinallyWorkflow
{
    #[WorkflowMethod(name: 'TimerInFinallyWorkflow')]
    public function handler(): iterable
    {
        try {
            yield Workflow::timer(100);
        } finally {
            Workflow::timer(1);
            Workflow::upsertMemo(['key' => 'value']);
        }
    }
}

#[Workflow\WorkflowInterface]
final class HeldCancellerWorkflow
{
    public static array $canceller = [];

    #[WorkflowMethod(name: 'HeldCancellerWorkflow')]
    public function handler(): iterable
    {
        $deferred = new Deferred(static function (): void {
            self::$canceller[] = Workflow::getInfo()->execution->getRunID();
        });
        $held = Promise::all([$deferred->promise()]);

        yield Workflow::timer(100);

        return $held;
    }
}

#[Workflow\WorkflowInterface]
final class BatchResumeWorkflow
{
    public static array $events = [];

    #[WorkflowMethod(name: 'BatchResumeWorkflow')]
    public function handler(): iterable
    {
        $deferred = new Deferred();
        Workflow::async(static function () use ($deferred): \Generator {
            try {
                yield $deferred->promise();
                self::$events[] = 'resumed';
            } finally {
                self::$events[] = 'waiter';
            }
        });

        try {
            yield Workflow::timer(1);
            $deferred->resolve(1);
            self::$events[] = Workflow::isReplaying() ? 'after evicted' : 'after';
            yield Workflow::timer(100);
        } finally {
            self::$events[] = 'main';
        }
    }
}
