<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;

/**
 * Handle to an in-flight Nexus operation started from a workflow.
 *
 * Models Java's `NexusOperationHandle<T>`: letting a workflow kick off an
 * operation and do other work before awaiting the result.
 *
 * ## Current wire limitation
 *
 * The underlying RoadRunner `ExecuteNexusOperation` command is atomic
 * start+wait — the operation token is not surfaced to the workflow until
 * completion. That means `getOperationToken()` always returns `null` for
 * the lifetime of the handle. The split API is provided so workflows are
 * source-compatible with a future wire extension that streams the token
 * out early.
 *
 * Expected usage:
 *
 * ```php
 * $handle = $stub->start('order.place', [$order]);
 * // ... workflow does other things ...
 * $result = yield $handle->getResult();
 * ```
 *
 * The handle is single-shot — calling `getResult()` multiple times returns
 * the same underlying promise.
 *
 * @template T
 */
final class NexusOperationHandle
{
    public function __construct(
        private readonly PromiseInterface $promise,
    ) {}

    /**
     * Returns the promise that resolves with the operation's result or
     * rejects if the operation failed / was cancelled.
     *
     * Safe to call multiple times — always returns the same promise.
     *
     * @return PromiseInterface<T>
     */
    public function getResult(): PromiseInterface
    {
        return $this->promise;
    }

    /**
     * Async operation token, if the handler responded asynchronously.
     *
     * Currently always `null` — the wire does not surface the token until
     * the operation completes (see class-level note). Reserved for
     * future expansion when RoadRunner streams the token on async start.
     */
    public function getOperationToken(): ?string
    {
        return null;
    }
}
