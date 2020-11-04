<?php

declare(strict_types=1);

use Spiral\Goridge\StreamRelay;
use Spiral\RoadRunner\Worker as RoadRunner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

require __DIR__ . '/../vendor/autoload.php';

//
// Exception Handling
//
if (\class_exists(CliDumper::class)) {
    CliDumper::$defaultOutput = 'php://stderr';
}
\set_exception_handler(fn (\Throwable $e) => \file_put_contents('php://stderr', (string)$e));
\set_error_handler(fn(...$args) => \file_put_contents('php://stderr', \json_encode($args, \JSON_PRETTY_PRINT)));


//
// Start Workflow and Activities
//

$rr = new RoadRunner(new StreamRelay(\STDIN, \STDOUT));

$factory = new \Temporal\Client\WorkerFactory($rr);
$factory->create()
    ->registerWorkflow(new \App\Workflow\PizzaDelivery())
    ->registerActivity(new \App\Activity\ExampleActivity())
;

$factory->run();
