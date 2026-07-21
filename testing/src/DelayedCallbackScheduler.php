<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * Fires client-side callbacks at simulated-time offsets by fast-forwarding the time-skipping
 * test server to each deadline in order. Use it to signal/query/cancel a workflow under test
 * along its virtual timeline without waiting in real time.
 */
final class DelayedCallbackScheduler
{
    /** @var list<array{delay: int, callback: \Closure(WorkflowStubInterface): void}> */
    private array $callbacks = [];

    private TimeSkipDriver $driver;

    /** @var \Closure(): bool|null */
    private ?\Closure $readyPredicate = null;

    public function __construct(
        private readonly WorkflowClientInterface $workflowClient,
        private readonly TestService $testService,
        int $timerPollAttempts = 50,
        int $timerPollIntervalMicroseconds = 100_000,
    ) {
        $this->driver = new TimeSkipDriver(
            $workflowClient,
            $testService,
            $timerPollAttempts,
            $timerPollIntervalMicroseconds,
        );
    }

    /**
     * @param int $delaySeconds simulated-time offset from workflow start; must be >= 0
     * @param \Closure(WorkflowStubInterface): void $callback receives the started stub
     */
    public function registerDelayedCallback(int $delaySeconds, \Closure $callback): self
    {
        if ($delaySeconds < 0) {
            throw new \InvalidArgumentException(\sprintf('Delayed callback offset must be >= 0, got %d.', $delaySeconds));
        }

        $this->callbacks[] = ['delay' => $delaySeconds, 'callback' => $callback];

        return $this;
    }

    /**
     * Convenience: signal the workflow under test at a simulated-time offset.
     */
    public function signalAfter(int $delaySeconds, string $signalName, mixed ...$signalArgs): self
    {
        return $this->registerDelayedCallback(
            $delaySeconds,
            static fn(WorkflowStubInterface $stub) => $stub->signal($signalName, ...$signalArgs),
        );
    }

    /**
     * Override the "workflow is ready" condition for workflows that block without scheduling a timer.
     *
     * @param \Closure(): bool|null $predicate
     */
    public function readyWhen(?\Closure $predicate): self
    {
        $this->readyPredicate = $predicate;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $startArgs
     */
    public function start(WorkflowStubInterface $stub, mixed ...$startArgs): void
    {
        $this->driver->assertOwnsTimeSkipping(self::class);
        $this->testService->lockTimeSkipping();

        try {
            $this->workflowClient->start($stub, ...$startArgs);
            $execution = $stub->getExecution();
            $this->driver->awaitWorkflowReady($execution, $this->readyPredicate);

            $sorted = $this->callbacks;
            \usort(
                $sorted,
                /**
                 * @param array{delay: int, callback: \Closure} $a
                 * @param array{delay: int, callback: \Closure} $b
                 */
                static fn(array $a, array $b): int => $a['delay'] <=> $b['delay'],
            );

            $elapsed = 0;
            foreach ($sorted as $entry) {
                $delta = $entry['delay'] - $elapsed;
                if ($delta > 0) {
                    $this->testService->unlockTimeSkippingWithSleep($delta);
                    $elapsed = $entry['delay'];
                }

                $this->fire($entry['delay'], $entry['callback'], $stub, $execution);
            }
        } finally {
            $this->testService->unlockTimeSkipping();
        }
    }

    private function fire(int $delay, \Closure $callback, WorkflowStubInterface $stub, WorkflowExecution $execution): void
    {
        if ($this->driver->isWorkflowClosed($execution)) {
            throw new \RuntimeException(\sprintf(
                'Delayed callback scheduled at +%ds could not fire: the workflow is no longer running.',
                $delay,
            ));
        }

        try {
            $callback($stub);
        } catch (\Throwable $e) {
            throw new \RuntimeException(\sprintf('Delayed callback scheduled at +%ds threw: %s', $delay, $e->getMessage()), 0, $e);
        }
    }
}
