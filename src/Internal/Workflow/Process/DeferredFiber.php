<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\InvalidSuspendException;
use Temporal\Internal\Declaration\MethodHandler;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowContextInterface;

/**
 * Lazily starts a workflow handler inside a managed Fiber.
 *
 * @internal
 * @psalm-internal Temporal
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class DeferredFiber
{
    private \Fiber $fiber;

    /** @var list<\Closure(\Throwable): mixed> */
    private array $catchers = [];

    private function __construct() {}

    /**
     * @param MethodHandler|\Closure(ValuesInterface): mixed $handler
     */
    public static function fromHandler(
        MethodHandler|\Closure $handler,
        ValuesInterface $values,
        WorkflowContextInterface $context,
    ): self {
        $self = new self();
        $self->fiber = new \Fiber(static function () use ($handler, $values, $context): mixed {
            Workflow::setCurrentContext($context);

            try {
                $result = $handler($values);

                if ($result instanceof \Generator) {
                    throw new InvalidSuspendException(
                        'Generator workflow handlers are no longer supported. '
                        . 'Call Temporal workflow APIs directly instead of yielding them.',
                    );
                }

                if ($result instanceof PromiseInterface) {
                    throw new InvalidSuspendException(
                        'Promise-returning workflow handlers are not supported. '
                        . 'Call direct workflow APIs, or await an async scope explicitly.',
                    );
                }

                return $result;
            } finally {
                Workflow::setCurrentContext(null);
            }
        });

        return $self;
    }

    public function start(): mixed
    {
        if ($this->fiber->isStarted()) {
            throw new \LogicException('Cannot start a workflow Fiber more than once.');
        }

        Awaiter::register($this->fiber);

        try {
            return $this->fiber->start();
        } catch (\Throwable $e) {
            $this->handleException($e);
        } finally {
            $this->unregisterIfTerminated();
        }
    }

    public function resume(mixed $value): mixed
    {
        if (!$this->fiber->isSuspended()) {
            throw new \LogicException('Cannot resume a workflow Fiber that is not suspended.');
        }

        try {
            return $this->fiber->resume($value);
        } catch (\Throwable $e) {
            $this->handleException($e);
        } finally {
            $this->unregisterIfTerminated();
        }
    }

    public function throw(\Throwable $exception): mixed
    {
        if (!$this->fiber->isSuspended()) {
            throw new \LogicException('Cannot throw an exception into a workflow Fiber that is not suspended.');
        }

        try {
            return $this->fiber->throw($exception);
        } catch (\Throwable $e) {
            $this->handleException($e);
        } finally {
            $this->unregisterIfTerminated();
        }
    }

    public function isStarted(): bool
    {
        return $this->fiber->isStarted();
    }

    public function isSuspended(): bool
    {
        return $this->fiber->isSuspended();
    }

    public function isTerminated(): bool
    {
        return $this->fiber->isTerminated();
    }

    public function getReturn(): mixed
    {
        if (!$this->fiber->isTerminated()) {
            throw new \LogicException('Cannot get the return value of a workflow Fiber that has not terminated.');
        }

        return $this->fiber->getReturn();
    }

    /**
     * @param \Closure(\Throwable): mixed $handler
     */
    public function catch(callable $handler): self
    {
        $this->catchers[] = $handler(...);
        return $this;
    }

    private function unregisterIfTerminated(): void
    {
        if ($this->fiber->isTerminated()) {
            Awaiter::unregister($this->fiber);
        }
    }

    private function handleException(\Throwable $e): never
    {
        foreach ($this->catchers as $catcher) {
            try {
                $catcher($e);
            } catch (\Throwable) {
            }
        }

        $this->catchers = [];

        throw $e;
    }
}
