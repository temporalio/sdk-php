<?php

declare(strict_types=1);

namespace Temporal\Tests\Benchmark;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\ParamProviders;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Temporal\Tests\Unit\Framework\CommandBatchMock;
use Temporal\Tests\Unit\Framework\Requests\InvokeSignal;
use Temporal\Tests\Unit\Framework\Requests\StartWorkflow;
use Temporal\Tests\Unit\Framework\WorkerFactoryMock;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

#[Revs(3000)]
#[Iterations(10)]
#[Warmup(100)]
#[BeforeMethods('createWorker')]
#[ParamProviders('provideShapes')]
final class EvictionBench
{
    private WorkerFactoryMock $factory;
    private string $shape;
    private int $run = 0;

    public function provideShapes(): iterable
    {
        foreach (['timer', 'temporal_all', 'react_all', 'scopes', 'then_chain', 'user_deferred', 'signal'] as $shape) {
            yield $shape => ['shape' => $shape];
        }
    }

    public function createWorker(array $params): void
    {
        $this->factory = WorkerFactoryMock::create();
        $this->factory->newWorker()->registerWorkflowObject(new EvictionWorkflow());
        $this->shape = $params['shape'];
        \gc_collect_cycles();
    }

    public function benchStartAndEvict(): void
    {
        $runId = 'run-' . $this->run++;
        $this->dispatch(new StartWorkflow($runId, 'EvictionWorkflow', $this->shape), new InvokeSignal($runId, 'wait'));
        $this->dispatch(new ServerRequest('DestroyWorkflow', new TickInfo(new \DateTimeImmutable()), ['runId' => $runId]));
    }

    private function dispatch(object ...$commands): void
    {
        $batch = new CommandBatchMock($commands, ['taskQueue' => 'default', 'tickTime' => (new \DateTimeImmutable())->format(\DATE_ATOM)]);
        $responses = (fn(): iterable => $this->dispatch($batch))->call($this->factory);
        foreach ($responses as $_) {
        }

        $this->factory->emit(LoopInterface::ON_FINALLY);
    }
}
