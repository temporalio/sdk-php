<?php

declare(strict_types=1);

use Temporal\Testing\Command;
use Temporal\Testing\Environment;
use Temporal\Tests\SearchAttributeTestInvoker;
use Temporal\Worker\FeatureFlags;

\chdir(__DIR__ . '/../..');
require_once __DIR__ . '/../../vendor/autoload.php';

$sysInfo = \Temporal\Testing\SystemInfo::detect();

$command = Command::fromEnv();
$environment = Environment::create();
$environment->startTemporalTestServer();
(new SearchAttributeTestInvoker())();
$environment->startRoadRunner(\implode(' ', [
    $sysInfo->rrExecutable,
    'serve',
    '-c', '.rr.silent.yaml',
    '-w', 'tests/Functional',
    '-o',
    'server.command=' . \implode(',', [
        PHP_BINARY,
        ...$command->getPhpBinaryArguments(),
        'worker.php',
        ...$command->getCommandLineArguments(),
    ]),
]));

\register_shutdown_function(static fn() => $environment->stop());

// Default feature flags
FeatureFlags::$warnOnWorkflowUnfinishedHandlers = false;
