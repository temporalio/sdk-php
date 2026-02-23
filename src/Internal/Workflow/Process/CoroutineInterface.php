<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

/**
 * Common interface for Generator-based and Fiber-based coroutine execution.
 *
 * Both {@see DeferredGenerator} and {@see DeferredFiber} implement this interface,
 * allowing {@see Scope} to drive coroutine execution uniformly.
 *
 * @internal
 * @psalm-internal Temporal\Internal
 */
interface CoroutineInterface
{
    /**
     * Whether the coroutine is still running (has more work to do).
     */
    public function isRunning(): bool;

    /**
     * Get the current suspended/yielded value.
     */
    public function current(): mixed;

    /**
     * Resume the coroutine with a resolved value.
     *
     * @note Does not throw coroutine's exceptions; use {@see catch()} to handle them.
     */
    public function send(mixed $value): mixed;

    /**
     * Resume the coroutine by throwing an exception into it.
     *
     * @note Does not throw coroutine's exceptions; use {@see catch()} to handle them.
     */
    public function throw(\Throwable $exception): void;

    /**
     * Get the return value of the completed coroutine.
     */
    public function getReturn(): mixed;

    /**
     * Add an exception handler.
     *
     * @param callable(\Throwable): mixed $handler
     */
    public function catch(callable $handler): static;
}
