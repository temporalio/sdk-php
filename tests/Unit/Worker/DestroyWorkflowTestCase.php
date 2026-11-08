<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use Internal\Destroy\Destroyable;
use React\Promise\Deferred;
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

    public function testEvictionRunsFinallyBlocksOfChildScopesBeforeTheirParents(): void
    {
        $this->worker->registerWorkflowObject(new NestedScopesWorkflow());
        $this->worker->runWorkflow('NestedScopesWorkflow');
        $this->worker->destroyWorkflow('NestedScopesWorkflow');

        $this->factory->run($this->worker);

        self::assertSame(['grandchild', 'child', 'main'], NestedScopesWorkflow::$finally);
    }

    public function testEvictionDoesNotResumeScopesFromFinallyBlocks(): void
    {
        $this->worker->registerWorkflowObject(new SiblingResolveWorkflow());
        $this->worker->runWorkflow('SiblingResolveWorkflow');
        $this->worker->destroyWorkflow('SiblingResolveWorkflow');

        $this->factory->run($this->worker);

        self::assertSame(['closer', 'waiter'], SiblingResolveWorkflow::$events);
    }

    public function testEvictionMarksScopesAsCancelled(): void
    {
        $this->worker->registerWorkflowObject(new CancelledScopeWorkflow());
        $this->worker->runWorkflow('CancelledScopeWorkflow');
        $this->worker->destroyWorkflow('CancelledScopeWorkflow');

        $this->factory->run($this->worker);

        self::assertSame([true, true], CancelledScopeWorkflow::$cancelled);
    }

    public function testEvictionRunsFinallyBlocksAndDestroyInReplayMode(): void
    {
        $this->worker->registerWorkflowObject(new ReplayModeWorkflow());
        $this->worker->runWorkflow('ReplayModeWorkflow');
        $this->worker->sendSignal('ReplayModeWorkflow', 'wait');
        $this->worker->destroyWorkflow('ReplayModeWorkflow');

        $this->factory->run($this->worker);

        \ksort(ReplayModeWorkflow::$replaying);
        self::assertSame(
            ['destroy' => true, 'finally' => true, 'run' => false, 'signal' => true],
            ReplayModeWorkflow::$replaying,
        );
    }

    protected function setUp(): void
    {
        EvictedWorkflow::$caught = [];
        EvictedWorkflow::$finally = [];
        ThrowingFinallyWorkflow::$destroyed = false;
        CommandInFinallyWorkflow::$failure = null;
        OnCancelWorkflow::$cancelled = false;
        DestroyOrderWorkflow::$events = [];
        NestedScopesWorkflow::$finally = [];
        SiblingResolveWorkflow::$events = [];
        CancelledScopeWorkflow::$cancelled = [];
        ReplayModeWorkflow::$replaying = [];
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

#[Workflow\WorkflowInterface]
final class NestedScopesWorkflow
{
    public static array $finally = [];

    #[WorkflowMethod(name: 'NestedScopesWorkflow')]
    public function handler(): iterable
    {
        Workflow::async(static function (): \Generator {
            Workflow::async(static fn(): \Generator => yield from self::guarded('grandchild'));
            yield from self::guarded('child');
        });
        yield from self::guarded('main');
    }

    private static function guarded(string $name): \Generator
    {
        try {
            yield Workflow::timer(100);
        } finally {
            self::$finally[] = $name;
        }
    }
}

#[Workflow\WorkflowInterface]
final class SiblingResolveWorkflow
{
    public static array $events = [];

    #[WorkflowMethod(name: 'SiblingResolveWorkflow')]
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
        Workflow::async(static function () use ($deferred): \Generator {
            try {
                yield Workflow::timer(100);
            } finally {
                self::$events[] = 'closer';
                $deferred->resolve(1);
            }
        });
        yield Workflow::timer(100);
    }
}

#[Workflow\WorkflowInterface]
final class CancelledScopeWorkflow
{
    public static array $cancelled = [];

    #[WorkflowMethod(name: 'CancelledScopeWorkflow')]
    public function handler(): iterable
    {
        $attached = Workflow::async(static fn(): \Generator => yield Workflow::timer(100));
        $detached = Workflow::asyncDetached(static fn(): \Generator => yield Workflow::timer(100));
        try {
            yield Workflow::timer(100);
        } finally {
            self::$cancelled = [$attached->isCancelled(), $detached->isCancelled()];
        }
    }
}

#[Workflow\WorkflowInterface]
final class ReplayModeWorkflow implements Destroyable
{
    public static array $replaying = [];

    #[WorkflowMethod(name: 'ReplayModeWorkflow')]
    public function handler(): iterable
    {
        self::$replaying['run'] = Workflow::isReplaying();
        try {
            yield Workflow::timer(100);
        } finally {
            self::$replaying['finally'] = Workflow::isReplaying();
        }
    }

    #[Workflow\SignalMethod(name: 'wait')]
    public function wait(): iterable
    {
        try {
            yield Workflow::timer(100);
        } finally {
            self::$replaying['signal'] = Workflow::isReplaying();
        }
    }

    public function destroy(): void
    {
        self::$replaying['destroy'] = Workflow::isReplaying();
    }
}
