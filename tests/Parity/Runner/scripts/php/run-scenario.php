<?php

declare(strict_types=1);

use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\Command;
use Temporal\Testing\Environment;
use Temporal\Tests\Parity\Framework\Runtime\Bootstrap;
use Temporal\Tests\Parity\Framework\Runtime\RRStarter;
use Temporal\Tests\Parity\Framework\Runtime\State;
use Temporal\Worker\Logger\StderrLogger;

$projectRoot = \dirname(__DIR__, 5);
\chdir($projectRoot);
require './vendor/autoload.php';

Bootstrap::init();

$logger = new StderrLogger();

$opts = \getopt('', ['scenario:', 'address:', 'namespace:', 'task-queue:']) ?: [];

$required = ['scenario', 'address', 'namespace', 'task-queue'];
foreach ($required as $key) {
    if (!isset($opts[$key]) || $opts[$key] === '') {
        $logger->error("missing required option --{$key}");
        exit(64);
    }
}

$scenarioDir = $opts['scenario'];
$address     = $opts['address'];
$namespace   = $opts['namespace'];
$taskQueue   = $opts['task-queue'];

if (!\is_dir($scenarioDir)) {
    $logger->error("scenario dir not found: {$scenarioDir}");
    exit(1);
}

$phpDir = $scenarioDir . '/php';
if (!\is_file($phpDir . '/scenario.php')) {
    $logger->error("missing scenario.php in {$phpDir}");
    exit(1);
}

\putenv('ALLOW_EXTERNAL_TEMPORAL_PROCESS=true');
\putenv("TEMPORAL_ADDRESS={$address}");
\putenv("TEMPORAL_NAMESPACE={$namespace}");
\putenv("PARITY_SCENARIO_DIR={$scenarioDir}");
\putenv("PARITY_PHP_TASK_QUEUE={$taskQueue}");
$_ENV['TEMPORAL_ADDRESS'] = $address;
$_ENV['TEMPORAL_NAMESPACE'] = $namespace;
$_ENV['PARITY_SCENARIO_DIR'] = $scenarioDir;
$_ENV['PARITY_PHP_TASK_QUEUE'] = $taskQueue;

$logger->info('booting parity launcher', [
    'scenario' => $scenarioDir,
    'namespace' => $namespace,
    'task_queue' => $taskQueue,
]);

$command = Command::fromCommandLine(['parity', "address={$address}", "namespace={$namespace}"]);
$rrConfigDir = $projectRoot . '/tests/Parity/Runner/scripts/php';

$runtime = new State(
    command: $command,
    rrConfigDir: $rrConfigDir,
    workDir: $projectRoot,
    testCasesDir: ['Temporal\\Tests\\Parity' => $projectRoot . '/tests/Parity'],
    activityWorkers: 1,
);

[$temporalHost, $temporalPort] = \explode(':', $address);
$probe = @\fsockopen($temporalHost, (int) $temporalPort, $errno, $errstr, 2.0);
if ($probe === false) {
    $logger->error("Temporal not listening at {$address}: {$errstr} ({$errno}). setup-temporal.sh should have started it; aborting.");
    exit(3);
}
\fclose($probe);
$logger->info('Temporal is up (managed externally by setup-temporal.sh)');

$environment = Environment::create();
$environment->startTemporalServer();
$rrRunner = new RRStarter($runtime, $environment);

$logger->info('starting RoadRunner');
$rrRunner->start();

$serviceClient = ServiceClient::create($address);

try {
    $serviceClient->getConnection()->connect(5);
    $logger->info("connected to Temporal at {$address}");
} catch (\Throwable $e) {
    $logger->error("cannot connect to Temporal at {$address}", ['error' => $e->getMessage()]);
    exit(3);
}

$workflowClient = WorkflowClient::create(
    serviceClient: $serviceClient,
    options: (new ClientOptions())->withNamespace($namespace),
)->withTimeout(30);

require $phpDir . '/scenario.php';

$runFn = null;
foreach (\get_defined_functions()['user'] ?? [] as $fn) {
    if ($fn === 'parity_php_run' || \str_ends_with($fn, '\\parity_php_run')) {
        $runFn = $fn;
        break;
    }
}
if ($runFn === null) {
    $logger->error("scenario.php must declare parity_php_run(WorkflowClientInterface, string \$taskQueue): string");
    exit(1);
}

try {
    $workflowId = $runFn($workflowClient, $taskQueue);
    if (!\is_string($workflowId) || $workflowId === '') {
        $logger->error('parity_php_run must return a non-empty workflow id');
        exit(2);
    }

    echo "WORKFLOW_ID={$workflowId}\n";
    $logger->info('scenario completed', ['workflow_id' => $workflowId]);
    exit(0);
} catch (\Throwable $e) {
    $logger->error('scenario failed', [
        'class' => $e::class,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    \fwrite(\STDERR, "\n=== RoadRunner stdout ===\n" . $rrRunner->getOutput());
    \fwrite(\STDERR, "\n=== RoadRunner stderr ===\n" . $rrRunner->getErrorOutput());
    exit(2);
}
