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

    public function testEvictionRunsTheAwaitedCancellerReadOnlyInItsRunContext(): void
    {
        $this->dispatch([new StartWorkflow('run-canceller', 'AwaitedCancellerWorkflow'), new DestroyWorkflow('run-canceller')]);

        self::assertSame(
            ['run-canceller', 'Temporal\Exception\IllegalStateException'],
            AwaitedCancellerWorkflow::$canceller,
        );
    }

    protected function setUp(): void
    {
        AwaitedCancellerWorkflow::$canceller = [];
        $this->factory = WorkerFactoryMock::create();
        $worker = $this->factory->newWorker();
        $worker->registerWorkflowObject(new MutexHandoffWorkflow());
        $worker->registerWorkflowObject(new SingleTimerWorkflow());
        $worker->registerWorkflowObject(new AwaitedCancellerWorkflow());

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
            try {
                Workflow::timer(5);
            } catch (\Throwable $e) {
                self::$canceller[] = $e::class;
            }
        });

        yield Promise::all([$deferred->promise(), Workflow::timer(100)]);
    }
}
