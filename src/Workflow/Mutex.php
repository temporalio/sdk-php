<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Promise;

/**
 * If a mutex is yielded without calling `lock()`, the Workflow will continue
 * only when the lock is released.
 *
 * ```
 *  $this->mutex = new Mutex();
 *
 *  // Continue only when the lock is released
 *  yield $this->mutex;
 * ```
 */
final class Mutex
{
    private bool $locked = false;

    /** @var Deferred[] */
    private array $waiters = [];

    /**
     * Lock the mutex.
     *
     * ```
     *  // Continue only when the lock is acquired
     *  yield $this->mutex->lock();
     * ```
     *
     * @return PromiseInterface A promise that resolves when the lock is acquired.
     */
    public function lock(): PromiseInterface
    {
        if (!$this->locked) {
            $this->locked = true;
            return Promise::resolve($this);
        }

        $deferred = new Deferred();
        $this->waiters[] = $deferred;

        return $deferred->promise();
    }

    /**
     * Try to lock the mutex.
     *
     * @return bool Returns true if the mutex was successfully locked, false otherwise.
     */
    public function tryLock(): bool
    {
        return !$this->locked and $this->locked = true;
    }

    /**
     * Release the lock.
     */
    public function unlock(): void
    {
        if ($this->waiters === []) {
            $this->locked = false;
            return;
        }

        \array_shift($this->waiters)->resolve($this);
    }

    /**
     * Check if the mutex is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }
}
