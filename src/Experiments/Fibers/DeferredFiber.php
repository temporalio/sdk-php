<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use Temporal\Internal\Workflow\Process\CoroutineInterface;

/**
 * A Fiber-based coroutine wrapper that mirrors DeferredGenerator behavior.
 *
 * When a workflow handler is a plain function (not a Generator), it runs inside a Fiber.
 * Async operations (activity calls, timers, etc.) suspend the Fiber with a PromiseInterface.
 * The Scope resumes the Fiber when the promise resolves.
 *
 * @experimental
 * @internal
 */
final class DeferredFiber implements CoroutineInterface
{
    private bool $finished = false;
    private mixed $suspendedValue = null;
    private mixed $returnValue = null;

    /** @var array<\Closure(\Throwable): mixed> */
    private array $catchers = [];

    /**
     * @param \Fiber $fiber The Fiber that has already been started (is suspended or terminated).
     * @param mixed $initialSuspendedValue The value from the first Fiber::suspend() call.
     */
    public function __construct(
        private \Fiber $fiber,
        mixed $initialSuspendedValue = null,
    ) {
        if ($fiber->isTerminated()) {
            $this->finished = true;
            $this->returnValue = $fiber->getReturn();
        } else {
            $this->suspendedValue = $initialSuspendedValue;
        }
    }

    public function isRunning(): bool
    {
        return !$this->finished;
    }

    public function current(): mixed
    {
        return $this->suspendedValue;
    }

    /**
     * Resume the Fiber with a resolved value.
     *
     * @note Does not throw Fiber's exceptions; use {@see catch()} to handle them.
     */
    public function send(mixed $value): mixed
    {
        if ($this->finished) {
            throw new \LogicException('Cannot send value to a Fiber that has already finished.');
        }

        try {
            $this->suspendedValue = $this->fiber->resume($value);
            $this->updateState();
            return $this->suspendedValue;
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Resume the Fiber by throwing an exception into it.
     *
     * @note Does not throw Fiber's exceptions; use {@see catch()} to handle them.
     */
    public function throw(\Throwable $exception): void
    {
        if ($this->finished) {
            throw new \LogicException('Cannot throw exception into a Fiber that has already finished.');
        }

        try {
            $this->suspendedValue = $this->fiber->throw($exception);
            $this->updateState();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    public function getReturn(): mixed
    {
        if (!$this->finished) {
            throw new \LogicException('Cannot get return value of a Fiber that has not finished.');
        }

        return $this->returnValue;
    }

    /**
     * @param callable(\Throwable): mixed $handler
     */
    public function catch(callable $handler): static
    {
        $this->catchers[] = $handler;
        return $this;
    }

    private function updateState(): void
    {
        if ($this->fiber->isTerminated()) {
            $this->finished = true;
            $this->returnValue = $this->fiber->getReturn();
            $this->suspendedValue = null;
        }
    }

    private function handleException(\Throwable $e): never
    {
        if ($this->finished) {
            throw $e;
        }

        $this->finished = true;
        foreach ($this->catchers as $catcher) {
            try {
                $catcher($e);
            } catch (\Throwable) {
                // Do nothing.
            }
        }

        $this->catchers = [];
        throw $e;
    }
}
