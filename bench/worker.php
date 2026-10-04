<?php

declare(strict_types=1);

use Temporal\Bench\BenchActivity;
use Temporal\Bench\BenchCpuWorkflow;
use Temporal\Bench\BenchIoWorkflow;
use Temporal\Bench\BenchKvWorkflow;
use Temporal\Bench\BenchParallelWorkflow;
use Temporal\Bench\BenchWorkflow;
use Temporal\Worker\Core\CoreWorkerFactory;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory;

require __DIR__ . '/autoload.php';

function registerBench(WorkerFactoryInterface $factory): void
{
    $factory
        ->newWorker(benchEnv('BENCH_TASK_QUEUE'))
        ->registerWorkflowTypes(BenchWorkflow::class, BenchParallelWorkflow::class, BenchIoWorkflow::class, BenchCpuWorkflow::class, BenchKvWorkflow::class)
        ->registerActivity(BenchActivity::class);
}

function runRoadRunner(): void
{
    $factory = WorkerFactory::create();
    registerBench($factory);
    $factory->run();
}

function runCore(): void
{
    $factory = CoreWorkerFactory::create(
        address: benchEnv('TEMPORAL_ADDRESS'),
        activityProcesses: (int) benchEnv('BENCH_ACTIVITY_WORKERS'),
    );
    registerBench($factory);
    exit($factory->run());
}

$transport = benchEnv('BENCH_TRANSPORT');
match ($transport) {
    'rr' => runRoadRunner(),
    'core', 'rr-core' => runCore(),
    default => throw new \InvalidArgumentException("Unknown BENCH_TRANSPORT: {$transport}"),
};
