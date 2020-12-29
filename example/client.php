<?php

declare(strict_types=1);

use App\CancellableWorkflow;
use App\CounterWorkflow;
use App\HeartbeatActivity;
use App\SimpleActivity;
use App\SimpleWorkflow;
use Spiral\Goridge\Relay;
use Temporal\Client\Worker;
use Temporal\Client\Worker\Transport\Goridge;
use Temporal\Client\Worker\Transport\RoadRunner;

require __DIR__ . '/../vendor/autoload.php';

// todo: wait for variables and use global constructors based on ENV
$worker = new Worker(
    new RoadRunner(Relay::create(Relay::STREAM)),
    new Goridge(Relay::create('tcp://127.0.0.1:6001'))
);

$worker->createAndRegister()
    ->addWorkflow(CounterWorkflow::class)
    ->addWorkflow(SimpleWorkflow::class)
    ->addWorkflow(CancellableWorkflow::class)
    ->addActivity(SimpleActivity::class)
    ->addActivity(HeartbeatActivity::class)
;

$worker->run();
