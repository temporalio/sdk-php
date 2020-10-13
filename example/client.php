<?php

use App\Workflow\PizzaDelivery;
use Spiral\Goridge\Transport\Connector\TcpConnector;
use Temporal\Client\Transport\GoRidge;
use Temporal\Client\Worker\Factory;

require __DIR__ . '/../vendor/autoload.php';

$transport = GoRidge::fromDuplexStream(TcpConnector::create('127.0.0.1:8080'));

$factory = Factory::create($transport)
    ->forWorkflows([new PizzaDelivery()])
    ->run()
;
