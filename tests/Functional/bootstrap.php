<?php

declare(strict_types=1);

use Temporal\Testing\Environment;
use Temporal\Tests\SearchAttributeTestInvoker;
use Temporal\Worker\FeatureFlags;

\chdir(__DIR__ . '/../..');
require_once __DIR__ . '/../../vendor/autoload.php';

$systemInfo = \Temporal\Testing\SystemInfo::detect();

$environment = Environment::create();
$environment->startTemporalTestServer();
(new SearchAttributeTestInvoker())();
$environment->startRoadRunner(
    rrCommand: \implode(' ', [
        $systemInfo->rrExecutable,
        'serve',
        '-c', '.rr.silent.yaml',
        '-w', 'tests/Functional',
        '-o',
        'server.command=' . \implode(',', [
            PHP_BINARY,
            ...$environment->command->getPhpBinaryArguments(),
            'worker.php',
            ...$environment->command->getCommandLineArguments(),
        ]),
    ]),
    configFile: 'tests/Functional/.rr.silent.yaml',
);

\register_shutdown_function(static fn() => $environment->stop());

// Default feature flags
FeatureFlags::$warnOnWorkflowUnfinishedHandlers = false;
