<?php

declare(strict_types=1);

use Temporal\Bench\BenchParallelWorkflow;
use Temporal\Bench\BenchWorkflow;
use Temporal\Worker\Core\CoreWorkerFactory;

require __DIR__ . '/../../autoload.php';

$factory = CoreWorkerFactory::create();
$factory
    ->newWorker(\getenv('BENCH_TASK_QUEUE') ?: 'bench')
    ->registerWorkflowTypes(BenchWorkflow::class, BenchParallelWorkflow::class);

exit((fn(): int => $this->serve('workflow'))->call($factory));
