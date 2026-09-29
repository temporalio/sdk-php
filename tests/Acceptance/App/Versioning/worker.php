<?php

declare(strict_types=1);

use Temporal\Common\Versioning\VersioningBehavior;
use Temporal\Common\Versioning\WorkerDeploymentVersion;
use Temporal\Worker\WorkerDeploymentOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\WorkerFactory;

\chdir(__DIR__ . '/../../../..');
require './vendor/autoload.php';

$argument = static function (string $name) use ($argv): string {
    foreach ($argv as $value) {
        if (\str_starts_with($value, $name . '=')) {
            return \substr($value, \strlen($name) + 1);
        }
    }

    throw new \RuntimeException("{$name} is required");
};

$factory = WorkerFactory::create();

$factory->newWorker(
    $argument('task-queue'),
    WorkerOptions::new()->withDeploymentOptions(
        WorkerDeploymentOptions::new()
            ->withUseVersioning(true)
            ->withVersion(WorkerDeploymentVersion::new($argument('deployment'), $argument('build-id')))
            ->withDefaultVersioningBehavior(VersioningBehavior::AutoUpgrade),
    ),
)->registerWorkflowTypes($argument('workflow'));

$factory->run();
