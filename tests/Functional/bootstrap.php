<?php

declare(strict_types=1);

use Temporal\Tests\CoreWorker;
use Temporal\Testing\Environment;
use Temporal\Testing\SystemInfo;
use Temporal\Tests\SearchAttributeTestInvoker;
use Temporal\Worker\FeatureFlags;

$rootDir = \dirname(__DIR__, 2);
$configDir = $rootDir . '/tests/Functional';
$configFile = $configDir . '/.rr.silent.yaml';

\chdir($rootDir);
require_once $rootDir . '/vendor/autoload.php';

$systemInfo = SystemInfo::detect();

$environment = Environment::create(systemInfo: $systemInfo);
$environment->startTemporalTestServer();
(new SearchAttributeTestInvoker())();
if (CoreWorker::enabled()) {
    $workerCommand = \implode(' ', [PHP_BINARY, ...$environment->command->getPhpBinaryArguments(), 'worker.php']);
    $coreWorker = CoreWorker::start(
        [
            $rootDir . DIRECTORY_SEPARATOR . $systemInfo->rrExecutable,
            'serve',
            '-c', '.rr.core.yaml',
            '-o', "service.workflow.command=$workerCommand",
            '-o', "service.activity.command=$workerCommand",
        ],
        $configDir,
        $rootDir . '/runtime/tests/functional-core-worker.log',
        [],
    );
    \register_shutdown_function(static fn() => $coreWorker->stop(5));
} else {
    $environment->startRoadRunner(
        rrCommand: [
            $rootDir . DIRECTORY_SEPARATOR . $systemInfo->rrExecutable,
            'serve',
            '-c', $configFile,
            '-w', $configDir,
            '-o',
            'server.command=' . \implode(',', [
                PHP_BINARY,
                ...$environment->command->getPhpBinaryArguments(),
                'worker.php',
                ...$environment->command->getCommandLineArguments(),
            ]),
        ],
        configFile: $configFile,
    );
}

\register_shutdown_function(static fn() => $environment->stop());

// Default feature flags
FeatureFlags::$warnOnWorkflowUnfinishedHandlers = false;
