<?php

declare(strict_types=1);

use Temporal\Bench\BenchActivity;
use Temporal\Bench\BenchCpuWorkflow;
use Temporal\Bench\BenchIoWorkflow;
use Temporal\Bench\BenchParallelWorkflow;
use Temporal\Bench\BenchWorkflow;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory;

require __DIR__ . '/autoload.php';

function registerBench(WorkerFactoryInterface $factory): void
{
    $factory
        ->newWorker(\getenv('BENCH_TASK_QUEUE') ?: 'bench')
        ->registerWorkflowTypes(BenchWorkflow::class, BenchParallelWorkflow::class, BenchIoWorkflow::class, BenchCpuWorkflow::class)
        ->registerActivity(BenchActivity::class);
}

function runRoadRunner(): void
{
    $factory = WorkerFactory::create();
    registerBench($factory);
    $factory->run();
}

$transport = \getenv('BENCH_TRANSPORT') ?: 'rr';
match ($transport) {
    'rr' => runRoadRunner(),
    default => throw new \InvalidArgumentException("Unknown BENCH_TRANSPORT: {$transport}"),
};
