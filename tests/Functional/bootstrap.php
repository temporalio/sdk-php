<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
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
    $kvStorage = new Process([$rootDir . DIRECTORY_SEPARATOR . $systemInfo->rrExecutable, 'serve', '-c', $configDir . '/.rr.kv.yaml', '-w', $configDir], timeout: null);
    $kvStorage->start();
    if (!$kvStorage->waitUntil(static fn(string $type, string $output): bool => \str_contains($output, 'RoadRunner server started'))) {
        throw new \RuntimeException('The RoadRunner KV storage did not start: ' . $kvStorage->getErrorOutput() . $kvStorage->getOutput());
    }
    $coreWorker = CoreWorker::start(
        [PHP_BINARY, 'worker.php'],
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
