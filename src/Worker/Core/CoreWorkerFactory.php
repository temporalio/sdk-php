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
use Coresdk\Workflow_activation\RemoveFromCache;
use Coresdk\Workflow_activation\RemoveFromCache\EvictionReason;
use Coresdk\Workflow_activation\WorkflowActivation;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Temporal\Api\History\V1\History;
use Temporal\Internal\Support\Facade;
use Temporal\Client\WorkflowClient;
use Temporal\Common\EnvConfig\Client\ConfigProfile;
use Temporal\Common\EnvConfig\ConfigClient;
use Temporal\Common\SdkVersion;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Plugin\PluginRegistry;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Worker\ServiceCredentials;
use Temporal\Worker\Transport\Command\CommandInterface;
use Temporal\Worker\Transport\Command\ServerResponseInterface;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\WorkerFactory;

class CoreWorkerFactory extends WorkerFactory
{
    private const ROLE_ALL = 'all';
    private const ROLE_WORKFLOW = 'workflow';
    private const ROLE_ACTIVITY = 'activity';
    private const POLL_TIMEOUT_MS = 500;
    private const FINALIZE_TIMEOUT_SECONDS = 2;
    private const MAX_ACTIVITY_POLLERS = 8;
    private const STICKY_SCHEDULE_TO_START_TIMEOUT_MS = 5000;
    private const STOP_TIMEOUT_SECONDS = 10;
    private const STOP_POLL_INTERVAL_US = 100_000;
    private const MIN_CHILD_UPTIME_SECONDS = 1;

    private string $address;
    private string $namespace;
    private ConfigProfile $connection;
    private int $workflowProcesses;
    private int $activityProcesses;
    private int $activityConcurrency;
    private static ?Bridge $replayBridge = null;
    private LoggerInterface $logger;
    private bool $stopping = false;

    public static function create(
        ?DataConverterInterface $converter = null,
        ?RPCConnectionInterface $rpc = null,
        ?ServiceCredentials $credentials = null,
        ?PluginRegistry $pluginRegistry = null,
        ?WorkflowClient $client = null,
        ?string $address = null,
        ?string $namespace = null,
        ?int $workflowProcesses = null,
        ?int $activityProcesses = null,
    ): static {
        $converter ??= DataConverter::createDefault();
        /** @psalm-suppress UnsafeInstantiation */
        $factory = new static($converter, $rpc ?? new ActivityTasks($converter), $credentials, $pluginRegistry, $client);
        $profile = ConfigClient::load();
        $factory->address = $address ?? $profile->address ?? '127.0.0.1:7233';
        $factory->namespace = $namespace ?? $profile->namespace ?? 'default';
        $factory->connection = new ConfigProfile(
            $factory->address,
            $factory->namespace,
            $credentials?->apiKey ?: $profile->apiKey,
            $profile->tlsConfig,
        );
        $factory->workflowProcesses = $workflowProcesses ?? (int) ($_SERVER['TEMPORAL_CORE_WORKFLOW_PROCESSES'] ?? 1);
        $factory->activityProcesses = $activityProcesses ?? (int) ($_SERVER['TEMPORAL_CORE_ACTIVITY_PROCESSES'] ?? 0);
        $factory->activityConcurrency = (int) ($_SERVER['TEMPORAL_CORE_ACTIVITY_CONCURRENCY'] ?? 1);
        $factory->logger = new StderrLogger();

        return $factory;
    }

    public function run(?HostConnectionInterface $host = null): int
    {
        if ($this->workflowProcesses + $this->activityProcesses <= 1) {
            return $this->serve($this->activityProcesses === 0 ? self::ROLE_ALL : self::ROLE_ACTIVITY);
        }

        $roles = [
            ...\array_fill(0, $this->workflowProcesses, $this->activityProcesses === 0 ? self::ROLE_ALL : self::ROLE_WORKFLOW),
            ...\array_fill(0, $this->activityProcesses, self::ROLE_ACTIVITY),
        ];

        return $this->supervise($roles);
    }

    public function replay(History $history, string $workflowId): ?RemoveFromCache
    {
        $taskQueue = (string) $history->getEvents()[0]?->getWorkflowExecutionStartedEventAttributes()?->getTaskQueue()?->getName();
        $worker = $this->queues->find($taskQueue);
        if ($worker === null) {
            throw new \OutOfRangeException(\sprintf('Cannot find a worker for task queue "%s"', $taskQueue));
        }

        $bridge = self::$replayBridge ??= new Bridge();
        $core = $bridge->newReplayer(
            ['workflow_id' => $workflowId] + $this->config($worker, self::ROLE_WORKFLOW),
            $history->serializeToString(),
        );
        $activations = new WorkflowActivations($this->converter, $this->dispatchCommands(...), $this->namespace, $taskQueue, $this->versioningBehaviors($worker));

        $failure = null;
        $bridge->pollWorkflowActivation($core, 0);
        while (true) {
            foreach ($bridge->nextEvents(self::POLL_TIMEOUT_MS) as [, $kind, $status, $data]) {
                if ($kind === Bridge::KIND_WORKFLOW_COMPLETED) {
                    throw new \RuntimeException('sdk-core completion failed: ' . $data);
                }
                if ($kind !== Bridge::KIND_WORKFLOW_ACTIVATION) {
                    continue;
                }
                if ($status === Bridge::STATUS_SHUTDOWN) {
                    break 2;
                }
                if ($status !== Bridge::STATUS_OK) {
                    throw new \RuntimeException('sdk-core poll failed: ' . $data);
                }

                $activation = new WorkflowActivation();
                $activation->mergeFromString($data);
                foreach ($activation->getJobs() as $job) {
                    $eviction = $job->getRemoveFromCache();
                    if ($eviction !== null && !\in_array($eviction->getReason(), [EvictionReason::CACHE_FULL, EvictionReason::LANG_REQUESTED], true)) {
                        $failure = $eviction;
                    }
                }
                $bridge->completeWorkflowActivation($core, 0, $activations->handle($data));
                $bridge->pollWorkflowActivation($core, 0);
            }
        }
        $this->finalize($bridge, [$core]);

        return $failure;
    }

    private static function pem(?string $value): ?string
    {
        if ($value === null || !\is_file($value)) {
            return $value;
        }

        return \file_get_contents($value);
    }

    /**
     * @param list<non-empty-string> $roles
     */
    private function supervise(array $roles): int
    {
        $children = [];
        $startedAt = [];
        foreach ($roles as $role) {
            $pid = $this->spawn($role);
            $children[$pid] = $role;
            $startedAt[$pid] = \microtime(true);
        }

        $deadline = null;
        $stop = function () use (&$children, &$deadline): void {
            $this->stopping = true;
            $deadline ??= \microtime(true) + $this->stopTimeout();
            foreach (\array_keys($children) as $pid) {
                \posix_kill($pid, \SIGTERM);
            }
        };
        \pcntl_async_signals(true);
        \pcntl_signal(\SIGTERM, $stop, false);
        \pcntl_signal(\SIGINT, $stop, false);

        $code = 0;
        while ($children !== []) {
            $pid = \pcntl_wait($status, $this->stopping ? \WNOHANG : 0);
            if ($pid === 0 && \microtime(true) >= $deadline) {
                foreach (\array_keys($children) as $child) {
                    \posix_kill($child, \SIGKILL);
                }
            }
            if ($pid === 0) {
                \usleep(self::STOP_POLL_INTERVAL_US);
                continue;
            }
            if ($pid < 0) {
                continue;
            }
            $role = $children[$pid];
            $uptime = \microtime(true) - $startedAt[$pid];
            unset($children[$pid], $startedAt[$pid]);
            $code = \max($code, \pcntl_wexitstatus($status));
            if ($this->stopping) {
                continue;
            }
            if ($uptime < self::MIN_CHILD_UPTIME_SECONDS) {
                $this->logger->error(\sprintf('Worker process (%s) exited during startup, stopping', $role));
                $stop();
                continue;
            }
            $pid = $this->spawn($role);
            $children[$pid] = $role;
            $startedAt[$pid] = \microtime(true);
        }

        return $code;
    }

    /**
     * @return array<string, int>
     */
    private function versioningBehaviors(WorkerInterface $worker): array
    {
        $behaviors = [];
        foreach ($worker->getWorkflows() as $workflow) {
            $behaviors[$workflow->getID()] = $workflow->getVersioningBehavior()->value;
        }

        return $behaviors;
    }

    private function stopTimeout(): float
    {
        $timeouts = [];
        foreach ($this->queues as $worker) {
            $timeout = $worker->getOptions()->workerStopTimeout;
            if ($timeout !== null) {
                $timeouts[] = CarbonInterval::instance($timeout)->totalSeconds;
            }
        }

        return $timeouts === [] ? self::STOP_TIMEOUT_SECONDS : \max($timeouts);
    }

    private function spawn(string $role): int
    {
        $pid = \pcntl_fork();
        if ($pid === 0) {
            \register_shutdown_function(fn() => $this->exitChild(1));
            $this->exitChild($this->serve($role));
        }

        return $pid;
    }

    private function exitChild(int $code): never
    {
        \fflush(\STDOUT);
        \fflush(\STDERR);
        \FFI::cdef('void _exit(int status);')->_exit($code);
    }

    private function serve(string $role): int
    {
        \pcntl_async_signals(true);
        \pcntl_signal(\SIGTERM, fn() => $this->stopping = true);
        \pcntl_signal(\SIGINT, fn() => $this->stopping = true);

        $bridge = new Bridge();
        $profiler = ($_SERVER['TEMPORAL_CORE_PROFILE'] ?? false) ? new Profiler($this->logger, $role) : null;
        $dispatch = $profiler === null ? $this->dispatchCommands(...) : function (array $commands, array $headers) use ($profiler): array {
            $startedAt = \hrtime(true);
            try {
                return $this->dispatchCommands($commands, $headers);
            } finally {
                $profiler->add('php-sdk-dispatch', $startedAt);
            }
        };
        if ($this->rpc instanceof ActivityTasks) {
            $this->rpc->bind($bridge, $dispatch);
        }

        $workers = [];
        foreach ($this->queues as $worker) {
            \assert($worker instanceof WorkerInterface);
            $taskQueue = $worker->getID();
            $workers[] = [
                'core' => $bridge->newWorker($this->config($worker, $role)),
                'taskQueue' => $taskQueue,
                'activations' => new WorkflowActivations($this->converter, $dispatch, $this->namespace, $taskQueue, $this->versioningBehaviors($worker)),
                'workflows' => $role !== self::ROLE_ACTIVITY,
            ];
        }

        $activityPolls = $role === self::ROLE_ACTIVITY ? \min(self::MAX_ACTIVITY_POLLERS, $this->activityConcurrency) : 1;
        $open = 0;
        foreach ($workers as $tag => $worker) {
            if ($worker['workflows']) {
                $bridge->pollWorkflowActivation($worker['core'], $tag);
                ++$open;
            }
            for ($i = 0; $i < $activityPolls; ++$i) {
                $bridge->pollActivityTask($worker['core'], $tag);
                ++$open;
            }
        }

        $shutdownRequested = false;
        $shutdown = function () use (&$shutdownRequested, $workers, $bridge): void {
            if (!$this->stopping || $shutdownRequested) {
                return;
            }
            $shutdownRequested = true;
            foreach ($workers as $worker) {
                $bridge->initiateShutdown($worker['core']);
            }
        };
        $concurrent = $role === self::ROLE_ACTIVITY && $this->activityConcurrency > 1;
        $handle = function (array $events) use (&$open, $workers, $bridge, $profiler, $concurrent): void {
            foreach ($events as [$tag, $kind, $status, $data]) {
                $worker = $workers[$tag];
                $startedAt = \hrtime(true);
                switch ($kind) {
                    case Bridge::KIND_WORKFLOW_ACTIVATION:
                        if ($status !== Bridge::STATUS_OK) {
                            $this->onPollFailure($status, $data, $open, static fn() => $bridge->pollWorkflowActivation($worker['core'], $tag));
                            break;
                        }
                        $bridge->completeWorkflowActivation($worker['core'], $tag, $worker['activations']->handle($data));
                        $bridge->pollWorkflowActivation($worker['core'], $tag);
                        $profiler?->add('workflow-activation', $startedAt);
                        break;

                    case Bridge::KIND_ACTIVITY_TASK:
                        if ($status !== Bridge::STATUS_OK) {
                            $this->onPollFailure($status, $data, $open, static fn() => $bridge->pollActivityTask($worker['core'], $tag));
                            break;
                        }
                        $bridge->pollActivityTask($worker['core'], $tag);
                        $run = function () use ($worker, $tag, $data, $bridge, $profiler, $startedAt): void {
                            $completion = $this->rpc->handle($worker['core'], $worker['taskQueue'], $data);
                            if ($completion !== null) {
                                $bridge->completeActivityTask($worker['core'], $tag, $completion);
                            }
                            $profiler?->add('activity-task', $startedAt);
                        };
                        if ($concurrent) {
                            EventLoop::queue(static function () use ($run): void {
                                $fiber = new \Fiber($run);
                                Facade::isolateFiber($fiber);
                                $fiber->start();
                            });
                        } else {
                            $run();
                        }
                        break;

                    case Bridge::KIND_WORKFLOW_COMPLETED:
                    case Bridge::KIND_ACTIVITY_COMPLETED:
                        $this->logger->error('sdk-core completion failed: ' . $data);
                        break;
                }
            }
        };

        if ($concurrent) {
            $this->runEventLoop($bridge, $handle, $shutdown, $open);
        }

        while ($open > 0) {
            $shutdown();
            $waitedAt = \hrtime(true);
            $events = $bridge->nextEvents(self::POLL_TIMEOUT_MS);
            $profiler?->add('wait', $waitedAt);
            $handle($events);
        }

        $profiler?->report();
        $this->finalize($bridge, \array_column($workers, 'core'));

        return 0;
    }

    /**
     * @param list<\FFI\CData> $cores
     */
    private function finalize(Bridge $bridge, array $cores): void
    {
        foreach ($cores as $tag => $core) {
            $bridge->finalizeShutdown($core, $tag);
        }
        $finalized = 0;
        $deadline = \microtime(true) + self::FINALIZE_TIMEOUT_SECONDS;
        while ($finalized < \count($cores) && \microtime(true) < $deadline) {
            foreach ($bridge->nextEvents(self::POLL_TIMEOUT_MS) as [, $kind]) {
                if ($kind === Bridge::KIND_SHUTDOWN) {
                    ++$finalized;
                }
            }
        }
    }

    private function runEventLoop(Bridge $bridge, \Closure $handle, \Closure $shutdown, int &$open): void
    {
        $pipe = \fopen('php://fd/' . $bridge->eventFd(), 'r');
        \stream_set_blocking($pipe, false);
        $pump = static function () use ($bridge, $handle, $pipe): void {
            \fread($pipe, 65536);
            $handle($bridge->nextEvents(0));
        };
        $readable = EventLoop::onReadable($pipe, $pump);
        $timer = EventLoop::repeat(self::POLL_TIMEOUT_MS / 1000, static function () use ($shutdown, $pump, &$open, &$readable, &$timer): void {
            $shutdown();
            $pump();
            if ($open === 0) {
                EventLoop::cancel($readable);
                EventLoop::cancel($timer);
            }
        });
        $pump();
        EventLoop::run();
    }

    private function onPollFailure(int $status, string $error, int &$open, \Closure $repoll): void
    {
        if ($status === Bridge::STATUS_SHUTDOWN) {
            --$open;
            return;
        }

        $this->logger->error('sdk-core poll failed: ' . $error);
        $repoll();
    }

    private function config(WorkerInterface $worker, string $role): array
    {
        $options = $worker->getOptions();
        $isActivity = $role === self::ROLE_ACTIVITY;
        $tls = $this->connection->tlsConfig;
        if ($tls?->disabled) {
            $tls = null;
        }

        return [
            'target_url' => ($tls === null ? 'http://' : 'https://') . $this->address,
            'client_name' => SdkVersion::SDK_NAME,
            'client_version' => SdkVersion::getSdkVersion(),
            'api_key' => $this->connection->apiKey === null ? null : (string) $this->connection->apiKey,
            'tls' => $tls === null ? null : [
                'server_root_ca_cert' => self::pem($tls->rootCerts),
                'domain' => $tls->serverName,
                'client_cert' => self::pem($tls->certChain),
                'client_private_key' => self::pem($tls->privateKey),
            ],
            'namespace' => $this->namespace,
            'task_queue' => $worker->getID(),
            'identity' => $options->identity ?: \getmypid() . '@' . \gethostname(),
            'workflows' => !$isActivity,
            'activities' => true,
            'deployment' => isset($options->deploymentOptions) ? $this->marshaller->marshal($options->deploymentOptions) : null,
            'no_remote_activities' => $role === self::ROLE_WORKFLOW,
            'max_cached_workflows' => $isActivity ? 0 : (int) ($_SERVER['TEMPORAL_CORE_MAX_CACHED_WORKFLOWS'] ?? 10000),
            'max_outstanding_workflow_tasks' => $options->maxConcurrentWorkflowTaskExecutionSize ?: 100,
            'max_outstanding_activities' => $isActivity ? $this->activityConcurrency : 1,
            'max_outstanding_local_activities' => 1,
            'max_concurrent_workflow_task_polls' => $options->maxConcurrentWorkflowTaskPollers ?: 4,
            'sticky_queue_schedule_to_start_timeout_ms' => $options->stickyScheduleToStartTimeout === null
                ? self::STICKY_SCHEDULE_TO_START_TIMEOUT_MS
                : (int) CarbonInterval::instance($options->stickyScheduleToStartTimeout)->totalMilliseconds,
            'max_concurrent_activity_task_polls' => $options->maxConcurrentActivityTaskPollers ?: \min(self::MAX_ACTIVITY_POLLERS, $isActivity ? $this->activityConcurrency : 1),
        ];
    }

    /**
     * @param list<CommandInterface> $commands
     * @return list<CommandInterface>
     */
    private function dispatchCommands(array $commands, array $headers): array
    {
        foreach ($commands as $command) {
            $this->env->update($command->getTickInfo());

            if ($command instanceof ServerResponseInterface) {
                $this->client->dispatch($command);
                continue;
            }

            $this->server->dispatch($command, $headers);
        }

        $this->tick();

        $outgoing = [];
        foreach ($this->responses as $command) {
            $outgoing[] = $command;
        }

        return $outgoing;
    }
}
