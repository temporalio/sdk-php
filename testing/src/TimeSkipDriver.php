<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * Shared time-skip driving logic for the client-side delayed-action helpers: exclusive-ownership
 * assertion, "workflow reached a blocking point" readiness polling, and closed-workflow detection.
 */
final class TimeSkipDriver
{
    public function __construct(
        private readonly WorkflowClientInterface $workflowClient,
        private readonly TestService $testService,
        private readonly int $pollAttempts = 50,
        private readonly int $pollIntervalMicroseconds = 100_000,
    ) {}

    /**
     * @param class-string $driver
     */
    public function assertOwnsTimeSkipping(string $driver): void
    {
        $delta = $this->testService->lockDelta();
        if ($delta === 0) {
            return;
        }

        throw new \LogicException(\sprintf(
            '%s must own time-skipping exclusively, but the lock counter is already held (delta %d). '
            . 'Do not drive it inside %s or alongside another time-skip driver; extend the plain %s instead.',
            $driver,
            $delta,
            TimeSkippingWorkflowTestCase::class,
            WorkflowTestCase::class,
        ));
    }

    /**
     * Waits until the workflow is ready for an injected action: the caller predicate if given
     * (tolerating transient query failures), otherwise the workflow's first scheduled timer.
     *
     * @param \Closure(): bool|null $ready
     */
    public function awaitWorkflowReady(WorkflowExecution $execution, ?\Closure $ready): void
    {
        for ($attempt = 0; $attempt < $this->pollAttempts; ++$attempt) {
            if ($this->reached($execution, $ready)) {
                return;
            }

            \usleep($this->pollIntervalMicroseconds);
        }

        throw new \RuntimeException(
            'Timed out waiting for the workflow to reach a blocking point before advancing the clock. '
            . 'Pass a readiness predicate via readyWhen() for workflows that do not schedule a timer.',
        );
    }

    public function isWorkflowClosed(WorkflowExecution $execution): bool
    {
        $terminal = [
            EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED,
            EventType::EVENT_TYPE_WORKFLOW_EXECUTION_FAILED,
            EventType::EVENT_TYPE_WORKFLOW_EXECUTION_CANCELED,
            EventType::EVENT_TYPE_WORKFLOW_EXECUTION_TIMED_OUT,
            EventType::EVENT_TYPE_WORKFLOW_EXECUTION_TERMINATED,
            EventType::EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW,
        ];

        foreach ($this->workflowClient->getWorkflowHistory($execution, pageSize: 250) as $event) {
            if (\in_array($event->getEventType(), $terminal, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Closure(): bool|null $ready
     */
    private function reached(WorkflowExecution $execution, ?\Closure $ready): bool
    {
        if ($ready !== null) {
            try {
                return $ready();
            } catch (\Throwable) {
                return false;
            }
        }

        foreach ($this->workflowClient->getWorkflowHistory($execution, pageSize: 50) as $event) {
            if ($event->getEventType() === EventType::EVENT_TYPE_TIMER_STARTED) {
                return true;
            }
        }

        return false;
    }
}
