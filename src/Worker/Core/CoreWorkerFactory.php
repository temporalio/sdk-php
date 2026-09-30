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
use Temporal\Worker\WorkflowPanicPolicy;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Internal\Interceptor\Pipeline;
use Temporal\WorkerFactory;

class CoreWorkerFactory extends WorkerFactory
{
    private const ENV_ROLE = 'TEMPORAL_CORE_ROLE';
    private const ROLE_ALL = 'all';
    private const ROLE_WORKFLOW = 'workflow';
    private const ROLE_ACTIVITY = 'activity';
    private const POLL_TIMEOUT_MS = 500;
    private const FINALIZE_TIMEOUT_SECONDS = 2;
    private const MAX_ACTIVITY_POLLERS = 8;
    private const WORKFLOW_TASK_POLLERS = 8;
    private const NONSTICKY_TO_STICKY_POLL_RATIO = 0.5;
    private const STICKY_SCHEDULE_TO_START_TIMEOUT_MS = 5000;
    private const STOP_TIMEOUT_SECONDS = 10;
    private const STOP_POLL_INTERVAL_US = 100_000;
    private const MIN_CHILD_UPTIME_SECONDS = 1;
    private const MAX_RESTART_DELAY_SECONDS = 30;
    private const MIN_CACHED_WORKFLOW_TASKS = 2;
    private const SIGNAL_EXIT_CODE_BASE = 128;

    private string $address;
    private string $namespace;
    private ConfigProfile $connection;
    private int $workflowProcesses;
    private int $activityProcesses;
    private int $activityConcurrency;
    private static ?Bridge $replayBridge = null;
    private LoggerInterface $logger;
    private bool $stopping = false;
    private bool $crashed = false;
    private ?int $supervisorPid = null;
    private static int $replayTag = 0;

    /** @var list<resource> */
    private array $processes = [];

    /** @var list<string>|null */
    private ?array $iniArguments = null;

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
        if ($rpc !== null && !$rpc instanceof ActivityTasks) {
            throw new \InvalidArgumentException(\sprintf('The sdk-core transport needs %s as the RPC connection', ActivityTasks::class));
        }
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
        $factory->activityProcesses = $activityProcesses ?? (int) ($_SERVER['TEMPORAL_CORE_ACTIVITY_PROCESSES'] ?? 1);
        $factory->activityConcurrency = (int) ($_SERVER['TEMPORAL_CORE_ACTIVITY_CONCURRENCY'] ?? 1);
        $factory->logger = new StderrLogger();

        return $factory;
    }

    public function run(?HostConnectionInterface $host = null): int
    {
        $role = \getenv(self::ENV_ROLE);
        if (\is_string($role) && $role !== '') {
            $this->supervisorPid = \posix_getppid();
            \register_shutdown_function(fn() => $this->exitChild(1));
            $this->exitChild($this->servePlugins($role));
        }

        if ($this->workflowProcesses + $this->activityProcesses <= 1) {
            return $this->servePlugins($this->activityProcesses === 0 ? self::ROLE_ALL : self::ROLE_ACTIVITY);
        }

        $roles = [
            ...\array_fill(0, $this->workflowProcesses, $this->activityProcesses === 0 ? self::ROLE_ALL : self::ROLE_WORKFLOW),
            ...\array_fill(0, $this->activityProcesses, self::ROLE_ACTIVITY),
        ];

        return $this->supervise($roles);
    }

    public function replay(History $history, string $workflowId): void
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
        $tag = ++self::$replayTag;
        try {
            $failure = $this->drainReplay($bridge, $core, $tag, $this->activations($worker, $this->dispatchCommands(...)));
        } finally {
            $this->finalize($bridge, [$tag => $core]);
            $bridge->freeWorker($core);
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    private static function pem(?string $value): ?string
    {
        if ($value === null || !\is_file($value)) {
            return $value;
        }

        return \file_get_contents($value);
    }

    private static function gracefulShutdownMs(WorkerInterface $worker): int
    {
        $timeout = $worker->getOptions()->workerStopTimeout;

        return $timeout === null ? 0 : (int) CarbonInterval::instance($timeout)->totalMilliseconds;
    }

    private function drainReplay(Bridge $bridge, \FFI\CData $core, int $tag, WorkflowActivations $activations): ?ReplayFailedException
    {
        $failure = null;
        $bridge->pollWorkflowActivation($core, $tag);
        while (true) {
            foreach ($bridge->nextEvents(self::POLL_TIMEOUT_MS) as [$eventTag, $kind, $status, $data]) {
                if ($eventTag !== $tag) {
                    continue;
                }
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
                        $failure = new ReplayFailedException($eviction->getMessage(), $eviction->getReason() === EvictionReason::NONDETERMINISM);
                    }
                }
                $bridge->completeWorkflowActivation($core, $tag, $activations->handle($data));
                $bridge->pollWorkflowActivation($core, $tag);
            }
        }

        return $failure;
    }

    /**
     * @param list<non-empty-string> $roles
     */
    private function supervise(array $roles): int
    {
        $children = [];
        foreach ($roles as $role) {
            $children[$this->spawn($role)] = [$role, \microtime(true), 0];
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
            $pid = \pcntl_wait($status, \WNOHANG);
            if ($pid <= 0) {
                if ($this->stopping && \microtime(true) >= $deadline) {
                    foreach (\array_keys($children) as $child) {
                        \posix_kill($child, \SIGKILL);
                    }
                }
                \usleep(self::STOP_POLL_INTERVAL_US);
                continue;
            }
            [$role, $startedAt, $restarts] = $children[$pid];
            unset($children[$pid]);
            $exitCode = \pcntl_wifsignaled($status) ? self::SIGNAL_EXIT_CODE_BASE + \pcntl_wtermsig($status) : \pcntl_wexitstatus($status);
            if ($this->stopping) {
                $code = \max($code, $exitCode);
                continue;
            }
            $this->logger->error(\sprintf('Worker process (%s) exited with code %d', $role, $exitCode));
            $uptime = \microtime(true) - $startedAt;
            if ($uptime < self::MIN_CHILD_UPTIME_SECONDS && $restarts === 0) {
                $this->logger->error(\sprintf('Worker process (%s) exited during startup, stopping', $role));
                $code = \max($code, $exitCode, 1);
                $stop();
                continue;
            }
            if ($uptime < self::MIN_CHILD_UPTIME_SECONDS) {
                \sleep(\min(self::MAX_RESTART_DELAY_SECONDS, 2 ** \min($restarts, 5)));
            }
            if ($this->stopping) {
                continue;
            }
            $children[$this->spawn($role)] = [$role, \microtime(true), $uptime < self::MIN_CHILD_UPTIME_SECONDS ? $restarts + 1 : 0];
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

        return ($timeouts === [] ? 0 : \max($timeouts)) + self::STOP_TIMEOUT_SECONDS;
    }

    private function spawn(string $role): int
    {
        if (\PHP_OS_FAMILY === 'Linux' && !\extension_loaded('grpc') && !Bridge::started()) {
            $pid = \pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException(\sprintf('Unable to fork a %s worker process', $role));
            }
            if ($pid === 0) {
                $this->supervisorPid = \posix_getppid();
                \register_shutdown_function(fn() => $this->exitChild(1));
                $this->exitChild($this->servePlugins($role));
            }

            return $pid;
        }

        $process = \proc_open(
            [\PHP_BINARY, ...$this->iniArguments(), \get_included_files()[0], ...\array_slice($_SERVER['argv'], 1)],
            [\STDIN, \STDOUT, \STDERR],
            $pipes,
            null,
            [...\getenv(), self::ENV_ROLE => $role],
        );
        if ($process === false) {
            throw new \RuntimeException(\sprintf('Unable to start a %s worker process', $role));
        }
        $this->processes[] = $process;

        return \proc_get_status($process)['pid'];
    }

    /**
     * @return list<string>
     */
    private function iniArguments(): array
    {
        if ($this->iniArguments !== null) {
            return $this->iniArguments;
        }

        $noIni = \php_ini_loaded_file() === false && \php_ini_scanned_files() === false ? ['-n'] : [];
        $probe = \json_decode(
            (string) \shell_exec(\implode(' ', \array_map(\escapeshellarg(...), [
                \PHP_BINARY,
                ...$noIni,
                '-r',
                'echo json_encode([ini_get_all(null, false), get_loaded_extensions(), get_loaded_extensions(true)]);',
            ]))),
            true,
        ) ?: [[], [], []];
        [$defaults, $extensions, $zendExtensions] = $probe;

        $this->iniArguments = $noIni;
        foreach (\array_diff(\get_loaded_extensions(), $extensions) as $extension) {
            \array_push($this->iniArguments, '-d', 'extension=' . $extension);
        }
        foreach (\array_diff(\get_loaded_extensions(true), $zendExtensions) as $extension) {
            \array_push($this->iniArguments, '-d', 'zend_extension=' . $extension);
        }
        foreach (\ini_get_all(null, false) as $name => $value) {
            if ($value !== null && ($defaults[$name] ?? null) !== $value) {
                \array_push($this->iniArguments, '-d', $name . '="' . \addcslashes((string) $value, '"\\') . '"');
            }
        }

        return $this->iniArguments;
    }

    private function exitChild(int $code): never
    {
        \fflush(\STDOUT);
        \fflush(\STDERR);
        \FFI::cdef('void _exit(int status);')->_exit($code);
    }

    private function servePlugins(string $role): int
    {
        return Pipeline::prepare($this->pluginRegistry->getPlugins(WorkerPluginInterface::class))
            ->with(fn(): int => $this->serve($role), 'run')($this);
    }

    private function serve(string $role): int
    {
        \pcntl_async_signals(true);
        \pcntl_signal(\SIGTERM, fn() => $this->stopping = true);
        \pcntl_signal(\SIGINT, fn() => $this->stopping = true);

        $bridge = Bridge::shared();
        $profiler = ($_SERVER['TEMPORAL_CORE_PROFILE'] ?? false) ? new Profiler($this->logger, $role) : null;
        $dispatch = $profiler === null ? $this->dispatchCommands(...) : function (array $commands, array $headers) use ($profiler): array {
            $startedAt = \hrtime(true);
            try {
                return $this->dispatchCommands($commands, $headers);
            } finally {
                $profiler->add('php-sdk-dispatch', $startedAt);
            }
        };
        $concurrent = $role === self::ROLE_ACTIVITY && $this->activityConcurrency > 1;
        \assert($this->rpc instanceof ActivityTasks);
        $this->rpc->bind($bridge, $dispatch, $concurrent);

        $workers = [];
        foreach ($this->queues as $worker) {
            \assert($worker instanceof WorkerInterface);
            $config = $this->config($worker, $role);
            if (!$config['workflows'] && !$config['activities']) {
                continue;
            }
            $workers[] = [
                'core' => $bridge->newWorker($config),
                'taskQueue' => $worker->getID(),
                'activations' => $this->activations($worker, $dispatch),
                'workflows' => $config['workflows'],
                'activities' => $config['activities'],
            ];
        }
        if ($workers === []) {
            $this->logger->info(\sprintf('No task queue needs a %s process, exiting', $role));
            return 0;
        }

        $activityPolls = $role === self::ROLE_ACTIVITY ? \min(self::MAX_ACTIVITY_POLLERS, $this->activityConcurrency) : 1;
        $open = 0;
        foreach ($workers as $tag => $worker) {
            if ($worker['workflows']) {
                $bridge->pollWorkflowActivation($worker['core'], $tag);
                ++$open;
            }
            for ($i = 0; $worker['activities'] && $i < $activityPolls; ++$i) {
                $bridge->pollActivityTask($worker['core'], $tag);
                ++$open;
            }
        }

        $shutdownRequested = false;
        $shutdown = function () use (&$shutdownRequested, $workers, $bridge): void {
            if ($this->supervisorPid !== null && \posix_getppid() !== $this->supervisorPid && !$this->stopping) {
                $this->logger->error('The supervisor process is gone, stopping');
                $this->stopping = true;
            }
            if (!$this->stopping || $shutdownRequested) {
                return;
            }
            $shutdownRequested = true;
            foreach ($workers as $worker) {
                $bridge->initiateShutdown($worker['core']);
            }
        };
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

        return $this->crashed ? 1 : 0;
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
            if (!$this->stopping) {
                $this->logger->error('sdk-core worker shut down unexpectedly, stopping the process');
                $this->crashed = true;
                $this->stopping = true;
            }
            return;
        }

        $this->logger->error('sdk-core poll failed: ' . $error);
        $repoll();
    }

    private function activations(WorkerInterface $worker, \Closure $dispatch): WorkflowActivations
    {
        return new WorkflowActivations(
            $this->converter,
            $dispatch,
            $this->namespace,
            $worker->getID(),
            $this->versioningBehaviors($worker),
            $worker->getOptions()->workflowPanicPolicy === WorkflowPanicPolicy::FailWorkflow,
        );
    }

    private function config(WorkerInterface $worker, string $role): array
    {
        $options = $worker->getOptions();
        $isActivity = $role === self::ROLE_ACTIVITY;
        $workflows = !$isActivity && !$options->disableWorkflowWorker;
        $remoteActivities = $role !== self::ROLE_WORKFLOW && !$options->localActivityWorkerOnly;
        $cachedWorkflows = $workflows ? (int) ($_SERVER['TEMPORAL_CORE_MAX_CACHED_WORKFLOWS'] ?? 10000) : 0;
        $minWorkflowTasks = $cachedWorkflows > 0 ? self::MIN_CACHED_WORKFLOW_TASKS : 1;
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
            'workflows' => $workflows,
            'activities' => $workflows || $remoteActivities,
            'deployment' => isset($options->deploymentOptions) ? $this->marshaller->marshal($options->deploymentOptions) : null,
            'build_id' => $options->buildID,
            'no_remote_activities' => !$remoteActivities,
            'graceful_shutdown_period_ms' => self::gracefulShutdownMs($worker),
            'max_worker_activities_per_second' => $options->workerActivitiesPerSecond ?: null,
            'max_task_queue_activities_per_second' => $options->taskQueueActivitiesPerSecond ?: null,
            'max_cached_workflows' => $cachedWorkflows,
            'max_outstanding_workflow_tasks' => \max($minWorkflowTasks, $options->maxConcurrentWorkflowTaskExecutionSize ?: 100),
            'max_outstanding_activities' => $isActivity ? $this->activityConcurrency : 1,
            'max_outstanding_local_activities' => 1,
            'max_concurrent_workflow_task_polls' => \max($minWorkflowTasks, $options->maxConcurrentWorkflowTaskPollers ?: self::WORKFLOW_TASK_POLLERS),
            'nonsticky_to_sticky_poll_ratio' => self::NONSTICKY_TO_STICKY_POLL_RATIO,
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
