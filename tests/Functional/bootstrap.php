<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Temporal\Tests\CoreWorker;
use Temporal\Testing\Environment;
use Temporal\Testing\SystemInfo;
use Temporal\Tests\SearchAttributeTestInvoker;
use Temporal\Worker\FeatureFlags;

const KV_STORAGE_START_TIMEOUT_SECONDS = 10;

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
    $kvStorage = new Process([$rootDir . DIRECTORY_SEPARATOR . $systemInfo->rrExecutable, 'serve', '-c', $configDir . '/.rr.kv.yaml', '-w', $configDir], timeout: null);
    $kvStorage->start();
    $kvDeadline = \microtime(true) + KV_STORAGE_START_TIMEOUT_SECONDS;
    while (!\str_contains($kvStorage->getOutput(), 'RoadRunner server started')) {
        if (!$kvStorage->isRunning() || \microtime(true) > $kvDeadline) {
            throw new \RuntimeException('The RoadRunner KV storage did not start: ' . $kvStorage->getErrorOutput() . $kvStorage->getOutput());
        }
        \usleep(50_000);
    }
    $coreWorker = CoreWorker::start(
        [PHP_BINARY, ...$environment->command->getPhpBinaryArguments(), 'worker.php'],
        $configDir,
        $rootDir . '/runtime/tests/functional-core-worker.log',
        ['TEMPORAL_CORE_WORKFLOW_PROCESSES' => 1, 'TEMPORAL_CORE_ACTIVITY_PROCESSES' => 1],
    );
    \register_shutdown_function(static function () use ($coreWorker, $kvStorage): void {
        $coreWorker->stop(5);
        $kvStorage->stop(5);
    });
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
