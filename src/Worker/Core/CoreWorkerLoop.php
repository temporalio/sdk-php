<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Activity_task\ActivityTask;
use Coresdk\ActivityTaskCompletion;
use Coresdk\Workflow_activation\WorkflowActivation;
use Coresdk\Workflow_completion\WorkflowActivationCompletion;
use Google\Protobuf\Internal\Message;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Support\Facade;

/**
 * @internal
 */
final class CoreWorkerLoop
{
    private const PIPE_READ_BYTES = 65536;

    /** @var list<CoreWorkerHandle> */
    private array $workers = [];

    private int $open = 0;
    private bool $stopping = false;
    private bool $shutdownRequested = false;
    private bool $crashed = false;
    private string $apiKeyInUse = '';

    /**
     * @param \Closure(): string $apiKey
     * @param null|\Closure(class-string<Message>, string): void $wire
     */
    public function __construct(
        private readonly Bridge $bridge,
        private readonly ActivityTasks $activityTasks,
        private readonly LoggerInterface $logger,
        private readonly bool $concurrent,
        private readonly ?int $supervisorPid,
        private readonly \Closure $apiKey,
        private readonly ?\Closure $wire = null,
    ) {}

    /**
     * @param \Closure(): list<CoreWorkerHandle> $workers
     */
    public function serve(\Closure $workers): int
    {
        \pcntl_async_signals(true);
        foreach (Supervisor::STOP_SIGNALS as $signal) {
            \pcntl_signal($signal, $this->requestStop(...));
        }
        try {
            $this->apiKeyInUse = ($this->apiKey)();
            $this->workers = $workers();

            return $this->workers === [] ? 0 : $this->run();
        } finally {
            foreach (Supervisor::STOP_SIGNALS as $signal) {
                \pcntl_signal($signal, \SIG_DFL);
            }
        }
    }

    private function run(): int
    {
        $this->startPolling();
        if ($this->stopping) {
            $this->initiateShutdown();
        }

        if ($this->concurrent) {
            $this->runEventLoop();
        }
        while ($this->open > 0) {
            $this->checkSupervisor();
            $this->updateApiKey();
            $this->handle($this->bridge->nextEvents(Bridge::POLL_TIMEOUT_MS));
        }

        $cores = \array_map(static fn(CoreWorkerHandle $worker): \FFI\CData => $worker->core, $this->workers);
        foreach ($this->bridge->shutdownWorkers($cores) as $tag => $error) {
            $this->logger->error(\sprintf('sdk-core worker for task queue "%s" did not finalize: %s', $this->workers[$tag]->taskQueue, $error));
        }

        return $this->crashed ? 1 : 0;
    }

    private function startPolling(): void
    {
        foreach ($this->workers as $tag => $worker) {
            if ($worker->workflows) {
                $this->bridge->pollWorkflowActivation($worker->core, $tag);
                ++$this->open;
            }
            if ($worker->activities) {
                $this->bridge->pollActivityTask($worker->core, $tag);
                ++$this->open;
            }
        }
    }

    private function requestStop(): void
    {
        $this->stopping = true;
        $this->initiateShutdown();
    }

    private function initiateShutdown(): void
    {
        if ($this->shutdownRequested || $this->open === 0) {
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

    private function updateApiKey(): void
    {
        $apiKey = ($this->apiKey)();
        if ($apiKey === $this->apiKeyInUse) {
            return;
        }
        $this->apiKeyInUse = $apiKey;
        foreach ($this->workers as $worker) {
            $this->bridge->setApiKey($worker->core, $apiKey);
        }
    }

    /**
     * @param list<array{int, int, int, string}> $events
     */
    private function handle(array $events): void
    {
        foreach ($events as [$tag, $kind, $status, $data]) {
            $worker = $this->workers[$tag];
            match ($kind) {
                Bridge::KIND_WORKFLOW_ACTIVATION => $this->onActivation($worker, $tag, $status, $data),
                Bridge::KIND_ACTIVITY_TASK => $this->onActivityTask($worker, $tag, $status, $data),
                Bridge::KIND_WORKFLOW_COMPLETED, Bridge::KIND_ACTIVITY_COMPLETED => $this->logger->error('sdk-core completion failed: ' . $data),
                default => null,
            };
        }
    }

    private function onActivation(CoreWorkerHandle $worker, int $tag, int $status, string $data): void
    {
        if ($status !== Bridge::STATUS_OK) {
            $this->onPollFailure($status, $data);
            return;
        }
        $this->wire?->__invoke(WorkflowActivation::class, $data);
        $completion = $worker->activations->handle($data);
        $this->wire?->__invoke(WorkflowActivationCompletion::class, $completion);
        $this->bridge->completeWorkflowActivation($worker->core, $tag, $completion);
        $this->bridge->pollWorkflowActivation($worker->core, $tag);
    }

    private function onActivityTask(CoreWorkerHandle $worker, int $tag, int $status, string $data): void
    {
        if ($status !== Bridge::STATUS_OK) {
            $this->onPollFailure($status, $data);
            return;
        }
        $this->bridge->pollActivityTask($worker->core, $tag);
        $this->wire?->__invoke(ActivityTask::class, $data);
        $run = function () use ($worker, $tag, $data): void {
            $completion = $this->activityTasks->handle($worker->core, $worker->taskQueue, $data);
            if ($completion !== null) {
                $this->wire?->__invoke(ActivityTaskCompletion::class, $completion);
                $this->bridge->completeActivityTask($worker->core, $tag, $completion);
            }
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

    private function onPollFailure(int $status, string $error): void
    {
        --$this->open;
        if ($this->stopping) {
            return;
        }
        $this->logger->error($status === Bridge::STATUS_SHUTDOWN
            ? 'sdk-core worker shut down unexpectedly, stopping the process'
            : 'sdk-core poll failed, stopping the process: ' . $error);
        $this->crashed = true;
        $this->requestStop();
    }

    /**
     * @psalm-suppress UnusedVariable, UndefinedVariable
     */
    private function runEventLoop(): void
    {
        $pipe = $this->bridge->openEventPipe();
        $pump = function () use ($pipe): void {
            \fread($pipe, self::PIPE_READ_BYTES);
            $this->handle($this->bridge->nextEvents(0));
        };
        $readable = EventLoop::onReadable($pipe, $pump);
        $timer = EventLoop::repeat(Bridge::POLL_TIMEOUT_MS / 1000, function () use ($pump, &$readable, &$timer): void {
            $this->checkSupervisor();
            $this->updateApiKey();
            $pump();
            if ($this->open === 0) {
                EventLoop::cancel($readable);
                EventLoop::cancel($timer);
                $this->bridge->closeEventPipe();
            }
        });
        $pump();
        EventLoop::run();
    }
}
