<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;

interface MutexInterface
{
    public function lock(): PromiseInterface;

    /**
     * Try to lock the mutex.
     *
     * @return bool true if the mutex was successfully locked, false otherwise.
     */
    public function tryLock(): bool;

    public function unlock(): void;

    public function isLocked(): bool;
}
