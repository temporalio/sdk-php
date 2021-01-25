<?php

declare(strict_types=1);

use Temporal\DataConverter\DataConverter;
use Temporal\WorkerFactory;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\Transport\Goridge;
use Temporal\Tests;
use Spiral\Goridge\Relay;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param string $dir
 * @return array<string>
 */
$getClasses = static function (string $dir): iterable {
    $files = glob($dir . '/*.php');

    foreach ($files as $file) {
        yield substr(basename($file), 0, -4);
    }
};

$factory = WorkerFactory::create();

$worker = $factory->newWorker('default');

// register all workflows
foreach ($getClasses(__DIR__ . '/Fixtures/src/Workflow') as $name) {
    $class = 'Temporal\\Tests\\Workflow\\' . $name;

    if (class_exists($class)) {
        $worker->registerWorkflowType($class);
    }
}

// register all activity
foreach ($getClasses(__DIR__ . '/Fixtures/src/Activity') as $name) {
    $worker->registerActivityType('Temporal\\Tests\\Activity\\' . $name);
}

$factory->run();
