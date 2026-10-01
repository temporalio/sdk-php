<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Carbon\CarbonInterval;
use Temporal\Internal\Bridge\BridgeConnection;
use Temporal\Common\SdkVersion;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Worker\WorkerInterface;

/**
 * @internal
 */
final class CoreWorkerConfig
{
    private const MAX_ACTIVITY_POLLERS = 8;
    private const WORKFLOW_TASK_POLLERS = 8;
    private const DEFAULT_MAX_OUTSTANDING_WORKFLOW_TASKS = 100;
    private const SEQUENTIAL_LOCAL_ACTIVITIES = 1;
    private const NONSTICKY_TO_STICKY_POLL_RATIO = 0.5;
    private const STICKY_SCHEDULE_TO_START_TIMEOUT_MS = 5000;
    private const MIN_CACHED_WORKFLOW_TASKS = 2;

    /**
     * @param MarshallerInterface<array> $marshaller
     */
    public function __construct(
        private readonly CoreOptions $options,
        private readonly MarshallerInterface $marshaller,
    ) {}

    /**
     * @param iterable<WorkerInterface> $workers
     */
    public function hasWork(iterable $workers, CoreRole $role): bool
    {
        foreach ($workers as $worker) {
            if (self::runsWorkflows($worker, $role) || self::runsRemoteActivities($worker, $role)) {
                return true;
            }
        }

        return false;
    }

    public function build(WorkerInterface $worker, CoreRole $role): array
    {
        $options = $worker->getOptions();
        $workflows = self::runsWorkflows($worker, $role);
        $cachedWorkflows = $workflows ? $this->options->maxCachedWorkflows : 0;
        $minWorkflowTasks = $cachedWorkflows > 0 ? self::MIN_CACHED_WORKFLOW_TASKS : 1;
        $activityConcurrency = $role === CoreRole::Activity ? $this->options->activityConcurrency : 1;

        return [
            'connection' => $this->connection($options->identity),
            'namespace' => $this->options->namespace,
            'task_queue' => $worker->getID(),
            'workflows' => $workflows,
            'local_activities' => $workflows,
            'remote_activities' => self::runsRemoteActivities($worker, $role),
            'deployment' => isset($options->deploymentOptions) ? $this->marshaller->marshal($options->deploymentOptions) : null,
            'build_id' => $options->buildID,
            'graceful_shutdown_period_ms' => self::milliseconds($options->workerStopTimeout) ?? 0,
            'max_worker_activities_per_second' => $options->workerActivitiesPerSecond ?: null,
            'max_task_queue_activities_per_second' => $options->taskQueueActivitiesPerSecond ?: null,
            'max_cached_workflows' => $cachedWorkflows,
            'max_outstanding_workflow_tasks' => \max($minWorkflowTasks, $options->maxConcurrentWorkflowTaskExecutionSize ?: self::DEFAULT_MAX_OUTSTANDING_WORKFLOW_TASKS),
            'max_outstanding_activities' => $activityConcurrency,
            'max_outstanding_local_activities' => self::SEQUENTIAL_LOCAL_ACTIVITIES,
            'max_concurrent_workflow_task_polls' => \max($minWorkflowTasks, $options->maxConcurrentWorkflowTaskPollers ?: self::WORKFLOW_TASK_POLLERS),
            'nonsticky_to_sticky_poll_ratio' => self::NONSTICKY_TO_STICKY_POLL_RATIO,
            'sticky_queue_schedule_to_start_timeout_ms' => self::milliseconds($options->stickyScheduleToStartTimeout) ?? self::STICKY_SCHEDULE_TO_START_TIMEOUT_MS,
            'max_concurrent_activity_task_polls' => $options->maxConcurrentActivityTaskPollers ?: \min(self::MAX_ACTIVITY_POLLERS, $activityConcurrency),
        ];
    }

    private static function runsWorkflows(WorkerInterface $worker, CoreRole $role): bool
    {
        return $role->runsWorkflows() && !$worker->getOptions()->disableWorkflowWorker;
    }

    private static function runsRemoteActivities(WorkerInterface $worker, CoreRole $role): bool
    {
        return $role->runsRemoteActivities() && !$worker->getOptions()->localActivityWorkerOnly;
    }

    private static function milliseconds(?\DateInterval $interval): ?int
    {
        return $interval === null ? null : (int) CarbonInterval::instance($interval)->totalMilliseconds;
    }

    private function connection(string $identity): array
    {
        $tls = $this->options->tls;

        return BridgeConnection::client(
            $this->options->address,
            $tls === null ? null : BridgeConnection::tls($tls->rootCerts, $tls->serverName, $tls->certChain, $tls->privateKey),
        ) + [
            'client_name' => SdkVersion::SDK_NAME,
            'client_version' => SdkVersion::getSdkVersion(),
            'identity' => $identity ?: \getmypid() . '@' . \gethostname(),
            'api_key' => $this->options->apiKey,
            'connect_timeout_ms' => BridgeConnection::CONNECT_TIMEOUT_MS,
            'grpc_compression' => $this->options->grpcCompression,
        ];
    }
}
