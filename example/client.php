<?php

declare(strict_types=1);

use Spiral\Goridge\StreamRelay;
use Spiral\RoadRunner\Worker as RoadRunner;
use Temporal\Client\WorkerFactory;

require __DIR__ . '/../vendor/autoload.php';

//
// Exception Handling
//
WorkerFactory::debug();

//
// Create RoadRunner Connection
//
$rr = new RoadRunner(new StreamRelay(\STDIN, \STDOUT));

//
// Start Workflow and Activities
//
$factory = new WorkerFactory($rr);

$factory->createWorker()
    ->registerWorkflow(\App\CounterWorkflow::class)
    ->registerWorkflow(\App\SimpleWorkflow::class)
    ->registerActivity(\App\SimpleActivity::class)
;

$factory->run();
