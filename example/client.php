<?php

declare(strict_types=1);


use App\CancellableWorkflow;
use App\CounterWorkflow;
use App\SimpleActivity;
use App\SimpleWorkflow;
use Temporal\Client\Worker;
use Temporal\Client\Worker\Transport\RoadRunner;

require __DIR__ . '/../vendor/autoload.php';

$worker = new Worker(RoadRunner::pipes());

$worker->createAndRegister()
    ->addWorkflow(CounterWorkflow::class)
    ->addWorkflow(SimpleWorkflow::class)
    ->addWorkflow(CancellableWorkflow::class)
    ->addActivity(SimpleActivity::class)
;

$worker->run();
