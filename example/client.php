<?php

use App\Workflow\PizzaDelivery;
use Spiral\Goridge\Transport\Connector\TcpConnector;
use Temporal\Client\Protocol\Transport\GoRidge;
use Temporal\Client\Worker;

require __DIR__ . '/../vendor/autoload.php';

$transport = GoRidge::fromDuplexStream(TcpConnector::create('127.0.0.1:8080'));

$worker = Worker::forWorkflows($transport)
    ->withWorkflow(new PizzaDelivery())
    ->run()
;
