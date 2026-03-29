<?php

declare(strict_types=1);

use Temporal\Testing\Environment;
use Temporal\Tests\SearchAttributeTestInvoker;
use Temporal\Worker\FeatureFlags;

\chdir(__DIR__ . '/../..');
require_once __DIR__ . '/../../vendor/autoload.php';

$systemInfo = \Temporal\Testing\SystemInfo::detect();

$environment = Environment::create();
if (!$environment->isTemporalRunning()) {
    $environment->startTemporalServer();
}
(new SearchAttributeTestInvoker())();
$command = $environment->command;
$environment->startRoadRunner(
    rrCommand: \implode(' ', [
        $systemInfo->rrExecutable,
        'serve',
        '-c', '.rr.silent.yaml',
        '-w', 'tests/Functional',
        '-o',
        'temporal.namespace=' . $command->namespace,
        '-o',
        'temporal.address=' . $command->address,
        '-o',
        'server.command=' . \implode(',', [
            PHP_BINARY,
            ...$command->getPhpBinaryArguments(),
            'worker.php',
            ...$command->getCommandLineArguments(),
        ]),
    ]),
    configFile: 'tests/Functional/.rr.silent.yaml',
);

\register_shutdown_function(static fn() => $environment->stop());

// Default feature flags
FeatureFlags::$warnOnWorkflowUnfinishedHandlers = false;
