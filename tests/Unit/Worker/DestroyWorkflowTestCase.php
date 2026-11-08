<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use Internal\Destroy\Destroyable;
use Temporal\Exception\IllegalStateException;
use Temporal\Promise;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Tests\Unit\Framework\WorkerMock;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

final class DestroyWorkflowTestCase extends AbstractUnit
{
    private WorkerFactoryInterface $factory;
    private WorkerMock $worker;

    public function testEvictionDoesNotThrowIntoWorkflowCode(): void
    {
        $this->evict();

        self::assertSame([], EvictedWorkflow::$caught);
    }

    public function testEvictionRunsFinallyBlocksInTheWorkflowContext(): void
    {
        $this->evict();

        $runId = EvictedWorkflow::$runId;
        \ksort(EvictedWorkflow::$finally);
        self::assertSame(
            ['all' => $runId, 'detached' => $runId, 'handler' => $runId, 'signal' => $runId, 'spawned' => $runId, 'timeout' => $runId],
            EvictedWorkflow::$finally,
        );
    }

    public function testEvictionReleasesTheWorkflowWithoutTheGarbageCollector(): void
    {
        $this->evict();

        self::assertNull(EvictedWorkflow::$instance->get());
    }

    public function testEvictionRunsFinallyBlocksBeforeTheInstanceIsDestroyed(): void
    {
        $this->worker->registerWorkflowObject(new DestroyOrderWorkflow());
        $this->worker->runWorkflow('DestroyOrderWorkflow');
        $this->worker->destroyWorkflow('DestroyOrderWorkflow');

        $this->factory->run($this->worker);

        self::assertSame(['finally', 'destroy'], DestroyOrderWorkflow::$events);
    }

    public function testEvictionRejectsCommandsFromFinallyBlocks(): void
    {
        $this->worker->registerWorkflowObject(new CommandInFinallyWorkflow());
        $this->worker->runWorkflow('CommandInFinallyWorkflow');
        $this->worker->destroyWorkflow('CommandInFinallyWorkflow');

        $this->factory->run($this->worker);

        self::assertSame(IllegalStateException::class, CommandInFinallyWorkflow::$failure);
    }

    public function testEvictionDoesNotRunOnCancelCallbacks(): void
    {
        $this->worker->registerWorkflowObject(new OnCancelWorkflow());
        $this->worker->runWorkflow('OnCancelWorkflow');
        $this->worker->destroyWorkflow('OnCancelWorkflow');

        $this->factory->run($this->worker);

        self::assertFalse(OnCancelWorkflow::$cancelled);
    }

    public function testEvictionCompletesWhenFinallyBlockThrows(): void
    {
        $this->worker->registerWorkflowObject(new ThrowingFinallyWorkflow());
        $this->worker->runWorkflow('ThrowingFinallyWorkflow');
        $this->worker->destroyWorkflow('ThrowingFinallyWorkflow');

        $this->factory->run($this->worker);

        self::assertTrue(ThrowingFinallyWorkflow::$destroyed);
    }

    protected function setUp(): void
    {
        EvictedWorkflow::$caught = [];
        EvictedWorkflow::$finally = [];
        ThrowingFinallyWorkflow::$destroyed = false;
        CommandInFinallyWorkflow::$failure = null;
        OnCancelWorkflow::$cancelled = false;
        DestroyOrderWorkflow::$events = [];
        $this->factory = WorkerFactoryMock::create();
        $this->worker = $this->factory->newWorker();
        \gc_collect_cycles();
        \gc_disable();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        \gc_enable();
        Workflow::setCurrentContext(null);

        parent::tearDown();
    }

    private function evict(): void
    {
        $this->worker->registerWorkflowObject(new EvictedWorkflow());
        $this->worker->runWorkflow('EvictedWorkflow');
        $this->worker->sendSignal('EvictedWorkflow', 'wait');
        $this->worker->sendSignal('EvictedWorkflow', 'spawn');
        $this->worker->destroyWorkflow('EvictedWorkflow');

        $this->factory->run($this->worker);
    }
}

#[Workflow\WorkflowInterface]
final class EvictedWorkflow
{
    public static array $caught = [];
    public static array $finally = [];
    public static \WeakReference $instance;
    public static string $runId;

    #[WorkflowMethod(name: 'EvictedWorkflow')]
    public function handler(): iterable
    {
        self::$instance = \WeakReference::create($this);
        self::$runId = Workflow::getInfo()->execution->getRunID();
        Workflow::asyncDetached(fn() => $this->guarded('detached', Workflow::timer(100)));
        Workflow::async(fn() => $this->guarded('timeout', Workflow::awaitWithTimeout(
            100,
            Workflow::awaitWithTimeout(50, static fn(): bool => false),
            static fn(): bool => false,
        )));
        Workflow::async(fn() => $this->guarded('all', Promise::all([Workflow::timer(100), Workflow::timer(200)])));
        yield from $this->guarded('handler', Workflow::await(static fn(): bool => false));
    }

    #[Workflow\SignalMethod(name: 'wait')]
    public function wait(): iterable
    {
        yield from $this->guarded('signal', Workflow::timer(100));
    }

    #[Workflow\SignalMethod(name: 'spawn')]
    public function spawn(): void
    {
        Workflow::asyncDetached(fn() => $this->guarded('spawned', Workflow::timer(100)));
    }

    private function guarded(string $name, mixed $promise): \Generator
    {
        try {
            yield $promise;
        } catch (\Throwable $e) {
            self::$caught[$name] = $e::class;
        } finally {
            self::$finally[$name] = Workflow::getInfo()->execution->getRunID();
        }
    }
}

#[Workflow\WorkflowInterface]
final class ThrowingFinallyWorkflow implements Destroyable
{
    public static bool $destroyed = false;

    #[WorkflowMethod(name: 'ThrowingFinallyWorkflow')]
    public function handler(): iterable
    {
        try {
            yield Workflow::timer(100);
        } finally {
            throw new \RuntimeException('finally fails');
        }
    }

    public function destroy(): void
    {
        self::$destroyed = true;
    }
}

#[Workflow\WorkflowInterface]
final class CommandInFinallyWorkflow
{
    public static ?string $failure = null;

    #[WorkflowMethod(name: 'CommandInFinallyWorkflow')]
    public function handler(): iterable
    {
        try {
            yield Workflow::timer(100);
        } finally {
            try {
                Workflow::timer(1);
            } catch (\Throwable $e) {
                self::$failure = $e::class;
            }
        }
    }
}

#[Workflow\WorkflowInterface]
final class OnCancelWorkflow
{
    public static bool $cancelled = false;

    #[WorkflowMethod(name: 'OnCancelWorkflow')]
    public function handler(): iterable
    {
        $scope = Workflow::async(static fn(): \Generator => yield Workflow::timer(100));
        $scope->onCancel(static function (): void {
            self::$cancelled = true;
        });

        yield Promise::all([$scope, Workflow::timer(100)]);
    }
}

#[Workflow\WorkflowInterface]
final class DestroyOrderWorkflow implements Destroyable
{
    public static array $events = [];

    #[WorkflowMethod(name: 'DestroyOrderWorkflow')]
    public function handler(): iterable
    {
        try {
            yield Workflow::timer(100);
        } finally {
            self::$events[] = 'finally';
        }
    }

    public function destroy(): void
    {
        self::$events[] = 'destroy';
    }
}
