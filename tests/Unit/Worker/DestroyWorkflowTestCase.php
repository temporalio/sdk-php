<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use Internal\Destroy\Destroyable;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEvictionLeavesNoGarbageCycles(): void
    {
        $this->evict();
        \gc_collect_cycles();

        $this->runAndEvict();

        self::assertSame(0, \gc_collect_cycles());
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
        $this->runAndEvict();
    }

    private function runAndEvict(): void
    {
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
