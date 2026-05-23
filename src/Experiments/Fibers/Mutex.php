<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use Temporal\Workflow\Mutex as BaseMutex;

/**
 * Fiber-aware Mutex wrapper.
 *
 * Wraps {@see BaseMutex} so that {@see lock()} auto-suspends the Fiber.
 * Use this instead of the base Mutex in Fiber-based workflows.
 *
 * @experimental
 */
final class Mutex
{
    private BaseMutex $inner;

    public function __construct()
    {
        $this->inner = new BaseMutex();
    }

    /**
     * Lock the mutex. Suspends the Fiber until the lock is acquired.
     */
    public function lock(): mixed
    {
        $promise = $this->inner->lock();

        if (FiberHelper::isInFiberMode()) {
            return FiberHelper::await($promise);
        }

        return $promise;
    }

    /**
     * Try to lock the mutex without waiting.
     */
    public function tryLock(): bool
    {
        return $this->inner->tryLock();
    }

    /**
     * Release the lock.
     */
    public function unlock(): void
    {
        $this->inner->unlock();
    }

    /**
     * Check if the mutex is locked.
     */
    public function isLocked(): bool
    {
        return $this->inner->isLocked();
    }

    /**
     * Get the underlying base Mutex for interop with non-Fiber code.
     */
    public function getInner(): BaseMutex
    {
        return $this->inner;
    }
}
