<?php

declare(strict_types=1);

use Google\Protobuf\Timestamp;
use Temporal\Api\Enums\V1\WorkflowExecutionStatus;
use Temporal\Api\Workflowservice\V1\CountWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsRequest;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

require __DIR__ . '/autoload.php';

const TEMPORAL_NAMESPACE = 'default';
const SCENARIO_WORKFLOW = ['seq' => 'BenchWorkflow', 'par' => 'BenchParallelWorkflow', 'noact' => 'BenchWorkflow', 'io' => 'BenchIoWorkflow', 'cpu' => 'BenchCpuWorkflow'];

$opts = \getopt('', ['scenario:', 'workflows:', 'activities:', 'payload:', 'concurrency:', 'timeout:', 'rate:', 'prefix:', 'part:', 'started-at:']);
$scenario = $opts['scenario'] ?? 'seq';
$workflows = (int) ($opts['workflows'] ?? 100);
$activities = $scenario === 'noact' ? 0 : (int) ($opts['activities'] ?? 1);
$payload = (int) ($opts['payload'] ?? 100);
$concurrency = \max(1, (int) ($opts['concurrency'] ?? 8));
$timeout = (float) ($opts['timeout'] ?? 600);
$rate = (float) ($opts['rate'] ?? 0);
$address = \getenv('TEMPORAL_ADDRESS') ?: BENCH_DEFAULT_ADDRESS;
$taskQueue = \getenv('BENCH_TASK_QUEUE') ?: BENCH_DEFAULT_TASK_QUEUE;
$workflowType = SCENARIO_WORKFLOW[$scenario] ?? throw new \InvalidArgumentException("Unknown scenario: {$scenario}");
$prefix = $opts['prefix'] ?? \sprintf('bench-%s-%s-', $scenario, \bin2hex(\random_bytes(4)));

function startWorkflows(string $address, string $taskQueue, string $workflowType, string $prefix, array $indexes, int $activities, int $payload, float $rate, float $startedAt): void
{
    $client = WorkflowClient::create(ServiceClient::create($address));
    foreach ($indexes as $i) {
        if ($rate > 0) {
            $delay = $startedAt + $i / $rate - \microtime(true);
            if ($delay > 0) {
                \usleep((int) ($delay * 1e6));
            }
        }
        $stub = $client->newUntypedWorkflowStub(
            $workflowType,
            WorkflowOptions::new()->withTaskQueue($taskQueue)->withWorkflowId($prefix . $i),
        );
        $client->start($stub, $activities, $payload);
    }
}

function seconds(Timestamp $time): float
{
    return $time->getSeconds() + $time->getNanos() / 1e9;
}

function percentile(array $sorted, float $p): float
{
    return $sorted[\max(0, (int) \ceil($p / 100 * \count($sorted)) - 1)];
}

if (isset($opts['part'])) {
    $part = (int) $opts['part'];
    $ids = [];
    for ($id = $part; $id < $workflows; $id += $concurrency) {
        $ids[] = $id;
    }
    startWorkflows($address, $taskQueue, $workflowType, $prefix, $ids, $activities, $payload, $rate, (float) $opts['started-at']);
    exit(0);
}

$startedAt = \microtime(true);
$starters = [];
for ($part = 0; $part < \min($concurrency, $workflows); $part++) {
    $starters[] = \proc_open(
        [
            \PHP_BINARY,
            ...$_SERVER['argv'],
            '--prefix=' . $prefix,
            '--part=' . $part,
            '--started-at=' . $startedAt,
        ],
        [\STDIN, \STDOUT, \STDERR],
        $pipes,
    );
}
foreach ($starters as $starter) {
    while (($status = \proc_get_status($starter))['running']) {
        if (\microtime(true) - $startedAt > $timeout) {
            foreach ($starters as $running) {
                \proc_terminate($running, \SIGKILL);
            }
            throw new \RuntimeException('A starter process timed out');
        }
        \usleep(20_000);
    }
    if ($status['exitcode'] !== 0) {
        throw new \RuntimeException('A starter process failed');
    }
}
$startPhase = \microtime(true) - $startedAt;

$service = ServiceClient::create($address);
$closedQuery = \sprintf('WorkflowId STARTS_WITH "%s" AND ExecutionStatus != "Running"', $prefix);
while ((int) $service->CountWorkflowExecutions(
    (new CountWorkflowExecutionsRequest())->setNamespace(TEMPORAL_NAMESPACE)->setQuery($closedQuery),
)->getCount() < $workflows) {
    if (\microtime(true) - $startedAt > $timeout) {
        throw new \RuntimeException('Timeout: workflows did not complete');
    }
    \usleep(200_000);
}
$clientWall = \microtime(true) - $startedAt;

$starts = [];
$closes = [];
$latencies = [];
$failed = 0;
$token = '';
do {
    $page = $service->ListWorkflowExecutions(
        (new ListWorkflowExecutionsRequest())
            ->setNamespace(TEMPORAL_NAMESPACE)
            ->setQuery(\sprintf('WorkflowId STARTS_WITH "%s"', $prefix))
            ->setPageSize(1000)
            ->setNextPageToken($token),
    );
    foreach ($page->getExecutions() as $info) {
        $start = seconds($info->getStartTime());
        $close = seconds($info->getCloseTime());
        $starts[] = $start;
        $closes[] = $close;
        $latencies[] = ($close - $start) * 1000;
        if ($info->getStatus() !== WorkflowExecutionStatus::WORKFLOW_EXECUTION_STATUS_COMPLETED) {
            $failed++;
        }
    }
    $token = $page->getNextPageToken();
} while ($token !== '');
\sort($latencies);

$wall = \max($closes) - \min($starts);
$result = [
    'scenario' => $scenario,
    'workflows' => $workflows,
    'activities_per_workflow' => $activities,
    'payload_bytes' => $payload,
    'concurrency' => $concurrency,
    'rate' => $rate,
    'failed' => $failed,
    'wall_s' => \round($wall, 3),
    'workflows_per_s' => \round($workflows / $wall, 1),
    'activities_per_s' => \round($workflows * $activities / $wall, 1),
    'latency_ms_p50' => \round(percentile($latencies, 50), 1),
    'latency_ms_p95' => \round(percentile($latencies, 95), 1),
    'latency_ms_p99' => \round(percentile($latencies, 99), 1),
    'latency_ms_max' => \round(\end($latencies), 1),
    'start_phase_s' => \round($startPhase, 3),
    'start_rate_per_s' => \round($workflows / $startPhase, 1),
    'client_wall_s' => \round($clientWall, 3),
    'id_prefix' => $prefix,
];

\fprintf(
    \STDERR,
    "%s: %d wf x %d act, %dB | wall %.2fs | %.1f wf/s | %.1f act/s | p50 %.0fms p95 %.0fms p99 %.0fms | start phase %.2fs (%.0f/s) | failed %d\n",
    $scenario, $workflows, $activities, $payload, $result['wall_s'], $result['workflows_per_s'], $result['activities_per_s'],
    $result['latency_ms_p50'], $result['latency_ms_p95'], $result['latency_ms_p99'], $startPhase, $result['start_rate_per_s'], $failed,
);
echo \json_encode($result), "\n";
