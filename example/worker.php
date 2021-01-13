<?php

declare(strict_types=1);

use App\HeartbeatActivity;
use App\SimpleActivity;
use Spiral\Goridge\Relay;
use Temporal\Worker;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\Transport\RoadRunner;
use App\Hello;
use Temporal\DataConverter;

require __DIR__ . '/../vendor/autoload.php';

// todo: wait for variables and use global constructors based on ENV
$worker = new Worker(
    DataConverter\DataConverter::createDefault(),
    new RoadRunner(Relay::create(Relay::PIPES)),
    new Goridge(Relay::create('tcp://127.0.0.1:6001'))
);

$taskQueue = $worker->createAndRegister();

// Register Workflows
$taskQueue
    ->addWorkflow(Hello\HelloWorkflow::class);

// Register Activities
$taskQueue
    ->addActivity(SimpleActivity::class)
    ->addActivity(HeartbeatActivity::class);

//
// Starts worker. This method will block execution until the command from the host process.
//
$worker->run();
