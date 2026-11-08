<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker;

use Internal\Destroy\Destroyable;
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
            ['detached' => $runId, 'handler' => $runId, 'signal' => $runId, 'spawned' => $runId],
            EvictedWorkflow::$finally,
        );
    }

    public function testEvictionReleasesTheWorkflowWithoutTheGarbageCollector(): void
    {
        $this->evict();

        self::assertNull(EvictedWorkflow::$instance->get());
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
        \gc_collect_cycles();
        \gc_disable();
        $this->factory = WorkerFactoryMock::create();
        $this->worker = $this->factory->newWorker();

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
