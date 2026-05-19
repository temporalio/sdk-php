<?php

declare(strict_types=1);

use Temporal\Testing\Command;
use Temporal\Tests\Parity\Framework\Runtime\Bootstrap;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Worker\WorkerInterface;
use Temporal\WorkerFactory;

\chdir(__DIR__ . '/../../../../..');
require './vendor/autoload.php';

Bootstrap::init();

$logger = new StderrLogger();

$scenarioDir = \getenv('PARITY_SCENARIO_DIR');
$phpTaskQueue = \getenv('PARITY_PHP_TASK_QUEUE');

if ($scenarioDir === false || $scenarioDir === '') {
    $logger->error('PARITY_SCENARIO_DIR env not set');
    exit(1);
}
if ($phpTaskQueue === false || $phpTaskQueue === '') {
    $logger->error('PARITY_PHP_TASK_QUEUE env not set');
    exit(1);
}

$scenarioPhp = $scenarioDir . '/php/scenario.php';
if (!\is_file($scenarioPhp)) {
    $logger->error("scenario.php not found: {$scenarioPhp}");
    exit(1);
}

require $scenarioPhp;

$registerFn = null;
foreach (\get_defined_functions()['user'] ?? [] as $fn) {
    if ($fn === 'parity_php_register' || \str_ends_with($fn, '\\parity_php_register')) {
        $registerFn = $fn;
        break;
    }
}
if ($registerFn === null) {
    $logger->error("scenario.php must declare parity_php_register(WorkerInterface): void");
    exit(1);
}

$taskQueue = $phpTaskQueue;
$logger->info("starting parity worker", ['task_queue' => $taskQueue, 'scenario_dir' => $scenarioDir]);

try {
    $command = Command::fromCommandLine($argv);
    $factory = WorkerFactory::create();
    $worker = $factory->newWorker($taskQueue);

    $registerFn($worker);

    $logger->info('parity worker registered, entering run loop', ['register_fn' => $registerFn]);
    $factory->run();
} catch (\Throwable $e) {
    $logger->error('parity worker crashed', [
        'class' => $e::class,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(2);
}
