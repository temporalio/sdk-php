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
 * Common interface for coroutine execution.
 *
 * Currently implemented by {@see DeferredGenerator}, which wraps either a plain
 * Generator handler or a Fiber-bridge Generator produced by {@see Scope::createFiberHandler()}.
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
     * Does not throw the coroutine's own exceptions; register a handler via
     * {@see self::catch()} to observe them.
     */
    public function send(mixed $value): mixed;

    /**
     * Resume the coroutine by throwing an exception into it.
     *
     * Does not throw the coroutine's own exceptions; register a handler via
     * {@see self::catch()} to observe them.
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
