<?php

use App\Activity\ExampleActivity;
use App\Workflow\PizzaDelivery;
use React\EventLoop\Factory;
use Spiral\Goridge\Transport\Connector\TcpConnector;
use Temporal\Client\Protocol\Transport\GoridgeTransport;
use Temporal\Client\RestartableExecutor;
use Temporal\Client\Worker;

require __DIR__ . '/../vendor/autoload.php';


//
// Creating an EventLoop. Factory will select the most suitable event loop
// implementation by himself.
//
$loop = Factory::create();

//
// Low-level transport layer creation. In this case, we will use
// goridge (RoadRunner) over the TCP stream connection.
//
$transport = GoridgeTransport::fromDuplexStream($loop, TcpConnector::create('127.0.0.1:8080'));

//
// And now we create a Temporal Worker, register the necessary workflows
// and activities there, and then launch it.
//
$worker = new Worker($transport, $loop);

$worker->addWorkflow(new PizzaDelivery());
$worker->addActivity(new ExampleActivity());

$worker->onError(function (\Throwable $e) {
    echo 'Error: ' . $e . "\n";
});

RestartableExecutor::new($worker)
    // Restart 2 times
    ->times(2)
    // Wait 2.5 seconds before next attempt
    ->waitForRestart(2.5)
    // Run worker
    ->run()
;
