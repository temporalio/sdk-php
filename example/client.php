<?php
declare(strict_types=1);

use Spiral\Goridge\StreamRelay;
use Spiral\RoadRunner\Worker as RoadRunner;

require __DIR__ . '/../vendor/autoload.php';

$rr = new RoadRunner(new StreamRelay(\STDIN, \STDOUT));

$factory = new \Temporal\Client\WorkerFactory($rr);
$factory->create()
    ->registerWorkflow(new \App\Workflow\PizzaDelivery())
    ->registerActivity(new \App\Activity\ExampleActivity())
;

$factory->start();
