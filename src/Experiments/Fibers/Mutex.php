<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\Mutex as BaseMutex;

/**
 * Fiber-aware wrapper around {@see BaseMutex}.
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
     * Acquire the lock.
     *
     * In Fiber mode suspends the current Fiber and returns the resolved
     * mutex once the lock is acquired. Outside Fiber mode returns the raw
     * {@see PromiseInterface} so the caller can `yield` it.
     *
     * @return BaseMutex|PromiseInterface<BaseMutex>
     */
    public function lock(): mixed
    {
        $promise = $this->inner->lock();

        if (FiberHelper::isInFiberMode()) {
            return FiberHelper::await($promise);
        }

        return $promise;
    }

    public function tryLock(): bool
    {
        return $this->inner->tryLock();
    }

    public function unlock(): void
    {
        $this->inner->unlock();
    }

    public function isLocked(): bool
    {
        return $this->inner->isLocked();
    }

    /**
     * Expose the wrapped {@see BaseMutex} for interop with code that types its
     * parameter against the base Mutex.
     */
    public function getInner(): BaseMutex
    {
        return $this->inner;
    }
}
