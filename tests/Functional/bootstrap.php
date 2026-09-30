<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
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
if (\getenv('TEMPORAL_WORKER_TRANSPORT') === 'core') {
    $kvStorage = new Process([$rootDir . DIRECTORY_SEPARATOR . $systemInfo->rrExecutable, 'serve', '-c', $configDir . '/.rr.kv.yaml', '-w', $configDir], timeout: null);
    $kvStorage->start();
    $kvStorage->waitUntil(static fn(string $type, string $output): bool => \str_contains($output, 'RoadRunner server started'));
    $coreWorkerLog = $rootDir . '/runtime/tests/functional-core-worker.log';
    @\mkdir(\dirname($coreWorkerLog), recursive: true);
    $coreWorker = Process::fromShellCommandline(
        \sprintf('exec %s worker.php >> %s 2>&1', \escapeshellarg(PHP_BINARY), \escapeshellarg($coreWorkerLog)),
        $configDir,
        ['TEMPORAL_CORE_WORKFLOW_PROCESSES' => 1, 'TEMPORAL_CORE_ACTIVITY_PROCESSES' => 1],
        timeout: null,
    );
    $coreWorker->start();
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
