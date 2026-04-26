<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;

/**
 * Handle to an in-flight Nexus operation started from a workflow.
 *
 * The current RR wire couples start+wait, so `getOperationToken()` returns
 * `null` until the wire surfaces it on async start.
 *
 * ```php
 * $handle = $stub->start('order.place', [$order]);
 * $result = yield $handle->getResult();
 * ```
 *
 * @template T
 */
final class NexusOperationHandle
{
    public function __construct(
        private readonly PromiseInterface $promise,
    ) {}

    /**
     * Promise resolves with the result, rejects on failure/cancel.
     * Safe to call multiple times.
     *
     * @return PromiseInterface<T>
     */
    public function getResult(): PromiseInterface
    {
        return $this->promise;
    }

    /**
     * Currently always `null` (wire limitation — see class-level note).
     */
    public function getOperationToken(): ?string
    {
        return null;
    }
}
