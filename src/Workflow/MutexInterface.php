<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;

interface MutexInterface
{
    /**
     * @return non-empty-string The name of the mutex.
     */
    public function getName(): string;

    public function lock(): PromiseInterface;

    public function unlock(): void;

    public function isLocked(): bool;
}
