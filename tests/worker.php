<?php

declare(strict_types=1);

use Temporal\DataConverter\DataConverter;
use Temporal\Worker;
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

$worker = new Worker(
    DataConverter::createDefault(),
    new RoadRunner(Relay::create(Relay::PIPES)),
    new Goridge(Relay::create('tcp://127.0.0.1:6001'))
);

$taskQueue = $worker->createAndRegister('default');

// register all workflows
foreach ($getClasses(__DIR__ . '/Fixtures/src/Workflow') as $name) {
    $taskQueue->addWorkflow('Temporal\\Tests\\Workflow\\' . $name);
}

// register all activity
foreach ($getClasses(__DIR__ . '/Fixtures/src/Activity') as $name) {
    $taskQueue->addActivity('Temporal\\Tests\\Activity\\' . $name);
}

$worker->run();
