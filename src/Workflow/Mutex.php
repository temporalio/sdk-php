<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Workflow;

/**
 * Use the mutex as an await condition when the Workflow should continue only
 * after the current owner releases it.
 *
 * ```
 *  $this->mutex = new Mutex();
 *
 *  // Continue only when the lock is released
 *  Workflow::await($this->mutex);
 * ```
 */
final class Mutex
{
    private bool $locked = false;

    /** @var list<object> FIFO acquisition tickets. */
    private array $waiters = [];

    /**
     * Lock the mutex.
     *
     * ```
     *  // Continue only when the lock is acquired
     *  $this->mutex->lock();
     * ```
     *
     * @return self The acquired mutex.
     */
    public function lock(): self
    {
        if ($this->tryLock()) {
            return $this;
        }

        $ticket = new \stdClass();
        $this->waiters[] = $ticket;
        $acquired = false;

        try {
            Workflow::await(
                fn(): bool => !$this->locked && ($this->waiters[0] ?? null) === $ticket,
            );
            $this->locked = true;
            $acquired = true;
            return $this;
        } finally {
            $wasFirst = ($this->waiters[0] ?? null) === $ticket;
            $index = \array_search($ticket, $this->waiters, true);
            if ($index !== false) {
                \array_splice($this->waiters, $index, 1);
            }

            if (!$acquired && $wasFirst && !$this->locked) {
                $context = Workflow::getCurrentContext();
                if ($context instanceof WorkflowContext) {
                    $context->resolveConditions();
                }
            }
        }
    }

    /**
     * Try to lock the mutex.
     *
     * @return bool Returns true if the mutex was successfully locked, false otherwise.
     */
    public function tryLock(): bool
    {
        if ($this->locked || $this->waiters !== []) {
            return false;
        }

        $this->locked = true;
        return true;
    }

    /**
     * Release the lock.
     */
    public function unlock(): void
    {
        $this->locked = false;
    }

    /**
     * Check if the mutex is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }
}
