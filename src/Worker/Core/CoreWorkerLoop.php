<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Temporal\Internal\Support\Facade;
use Temporal\Worker\WorkerInterface;

/**
 * @internal
 */
final class CoreWorkerLoop
{
    private const POLL_RETRY_DELAY_SECONDS = 1;
    private const PIPE_READ_BYTES = 65536;

    /** @var array<int, CoreWorkerHandle> */
    private array $workers = [];

    private int $open = 0;
    private bool $stopping = false;
    private bool $shutdownRequested = false;
    private bool $crashed = false;
    private bool $concurrent = false;
    private ?int $supervisorPid = null;
    private ?Profiler $profiler = null;

    /**
     * @param \Closure(list<\Temporal\Worker\Transport\Command\CommandInterface>, array): list<\Temporal\Worker\Transport\Command\CommandInterface> $dispatch
     * @param \Closure(WorkerInterface, \Closure): WorkflowActivations $activations
     */
    public function __construct(
        private readonly Bridge $bridge,
        private readonly CoreOptions $options,
        private readonly CoreWorkerConfig $config,
        private readonly ActivityTasks $activityTasks,
        private readonly \Closure $dispatch,
        private readonly \Closure $activations,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param iterable<WorkerInterface> $queues
     */
    public function serve(iterable $queues, CoreRole $role, bool $supervised): int
    {
        $this->profiler = $this->options->profile ? new Profiler($this->logger, $role->value) : null;
        $this->concurrent = $role === CoreRole::Activity && $this->options->activityConcurrency > 1;
        $this->supervisorPid = $supervised ? \posix_getppid() : null;
        $dispatch = $this->profiledDispatch();
        $this->activityTasks->bind($this->bridge, $dispatch);

        foreach ($queues as $worker) {
            $config = $this->config->build($worker, $role);
            if ($config['workflows'] || $config['remote_activities']) {
                $this->workers[] = new CoreWorkerHandle(
                    $this->bridge->newWorker($config),
                    $worker->getID(),
                    ($this->activations)($worker, $dispatch),
                    $config['workflows'],
                    $config['local_activities'] || $config['remote_activities'],
                );
            }
        }
        if ($this->workers === []) {
            $this->logger->info(\sprintf('No task queue needs a %s process, exiting', $role->value));
            return 0;
        }

        $this->startPolling($this->config->activityPolls($role));
        \pcntl_async_signals(true);
        \pcntl_signal(\SIGTERM, $this->requestStop(...));
        \pcntl_signal(\SIGINT, $this->requestStop(...));

        if ($this->concurrent) {
            $this->runEventLoop();
        }
        while ($this->open > 0) {
            $this->checkSupervisor();
            $waitedAt = $this->startTimer();
            $events = $this->bridge->nextEvents(Bridge::POLL_TIMEOUT_MS);
            $this->profiler?->add('wait', $waitedAt);
            $this->handle($events);
        }

        $this->profiler?->report();
        foreach ($this->bridge->finalizeWorkers(\array_map(static fn(CoreWorkerHandle $worker): \FFI\CData => $worker->core, $this->workers)) as $tag => $error) {
            $this->logger->error(\sprintf('sdk-core worker for task queue "%s" did not finalize: %s', $this->workers[$tag]->taskQueue, $error));
        }

        return $this->crashed ? 1 : 0;
    }

    private function profiledDispatch(): \Closure
    {
        $profiler = $this->profiler;
        if ($profiler === null) {
            return $this->dispatch;
        }

        $dispatch = $this->dispatch;

        return static function (array $commands, array $headers) use ($dispatch, $profiler): array {
            $startedAt = \hrtime(true);
            try {
                return $dispatch($commands, $headers);
            } finally {
                $profiler->add('php-sdk-dispatch', $startedAt);
            }
        };
    }

    private function startTimer(): int
    {
        return $this->profiler === null ? 0 : (int) \hrtime(true);
    }

    private function startPolling(int $activityPolls): void
    {
        foreach ($this->workers as $tag => $worker) {
            if ($worker->workflows) {
                $this->bridge->pollWorkflowActivation($worker->core, $tag);
                ++$this->open;
            }
            for ($i = 0; $worker->activities && $i < $activityPolls; ++$i) {
                $this->bridge->pollActivityTask($worker->core, $tag);
                ++$this->open;
            }
        }
    }

    private function requestStop(): void
    {
        $this->stopping = true;
        if ($this->shutdownRequested) {
            return;
        }
        $this->shutdownRequested = true;
        foreach ($this->workers as $worker) {
            $this->bridge->initiateShutdown($worker->core);
        }
    }

    private function checkSupervisor(): void
    {
        if ($this->supervisorPid !== null && !$this->stopping && \posix_getppid() !== $this->supervisorPid) {
            $this->logger->error('The supervisor process is gone, stopping');
            $this->requestStop();
        }
    }

    /**
     * @param list<array{int, int, int, string}> $events
     */
    private function handle(array $events): void
    {
        foreach ($events as [$tag, $kind, $status, $data]) {
            $worker = $this->workers[$tag];
            $startedAt = $this->startTimer();
            match ($kind) {
                Bridge::KIND_WORKFLOW_ACTIVATION => $this->onActivation($worker, $tag, $status, $data, $startedAt),
                Bridge::KIND_ACTIVITY_TASK => $this->onActivityTask($worker, $tag, $status, $data, $startedAt),
                Bridge::KIND_WORKFLOW_COMPLETED, Bridge::KIND_ACTIVITY_COMPLETED => $this->logger->error('sdk-core completion failed: ' . $data),
                default => null,
            };
        }
    }

    private function onActivation(CoreWorkerHandle $worker, int $tag, int $status, string $data, int $startedAt): void
    {
        $repoll = fn() => $this->bridge->pollWorkflowActivation($worker->core, $tag);
        if ($status !== Bridge::STATUS_OK) {
            $this->onPollFailure($status, $data, $repoll);
            return;
        }
        $this->bridge->completeWorkflowActivation($worker->core, $tag, $worker->activations->handle($data));
        $repoll();
        $this->profiler?->add('workflow-activation', $startedAt);
    }

    private function onActivityTask(CoreWorkerHandle $worker, int $tag, int $status, string $data, int $startedAt): void
    {
        if ($status !== Bridge::STATUS_OK) {
            $this->onPollFailure($status, $data, fn() => $this->bridge->pollActivityTask($worker->core, $tag));
            return;
        }
        $this->bridge->pollActivityTask($worker->core, $tag);
        $run = function () use ($worker, $tag, $data, $startedAt): void {
            $completion = $this->activityTasks->handle($worker->core, $worker->taskQueue, $data);
            if ($completion !== null) {
                $this->bridge->completeActivityTask($worker->core, $tag, $completion);
            }
            $this->profiler?->add('activity-task', $startedAt);
        };
        if (!$this->concurrent) {
            $run();
            return;
        }
        EventLoop::queue(static function () use ($run): void {
            $fiber = new \Fiber($run);
            Facade::isolateFiber($fiber);
            $fiber->start();
        });
    }

    private function onPollFailure(int $status, string $error, \Closure $repoll): void
    {
        if ($status === Bridge::STATUS_SHUTDOWN) {
            --$this->open;
            if (!$this->stopping) {
                $this->logger->error('sdk-core worker shut down unexpectedly, stopping the process');
                $this->crashed = true;
                $this->requestStop();
            }
            return;
        }

        $this->logger->error(\sprintf('sdk-core poll failed, retrying in %d s: %s', self::POLL_RETRY_DELAY_SECONDS, $error));
        if ($this->concurrent) {
            EventLoop::delay(self::POLL_RETRY_DELAY_SECONDS, $repoll);
            return;
        }
        \sleep(self::POLL_RETRY_DELAY_SECONDS);
        $repoll();
    }

    private function runEventLoop(): void
    {
        $pipe = $this->bridge->eventPipe();
        $pump = function () use ($pipe): void {
            \fread($pipe, self::PIPE_READ_BYTES);
            $this->handle($this->bridge->nextEvents(0));
        };
        $readable = EventLoop::onReadable($pipe, $pump);
        $timer = EventLoop::repeat(Bridge::POLL_TIMEOUT_MS / 1000, function () use ($pump, &$readable, &$timer): void {
            $this->checkSupervisor();
            $pump();
            if ($this->open === 0) {
                EventLoop::cancel($readable);
                EventLoop::cancel($timer);
            }
        });
        $pump();
        EventLoop::run();
    }
}
