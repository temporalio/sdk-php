<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Coroutine;

class Stack implements CoroutineInterface
{
    /**
     * @var int
     */
    public const KEY_COROUTINE = 0x00;

    /**
     * @var int
     */
    public const KEY_RESOLVER = 0x01;

    /**
     * @var array{0: CoroutineInterface, 1: \Closure}
     */
    private array $stack = [];

    /**
     * @var bool
     */
    private bool $running = false;

    /**
     * @param iterable $iterator
     * @param \Closure|null $then
     */
    public function __construct(iterable $iterator, \Closure $then = null)
    {
        $this->push($iterator, $then);
    }

    /**
     * @return CoroutineInterface|null
     */
    private function top(): ?CoroutineInterface
    {
        $last = \end($this->stack);

        if ($last === false) {
            return null;
        }

        return $last[self::KEY_COROUTINE];
    }

    /**
     * @param iterable $iter
     */
    public function push(iterable $iter, \Closure $then = null): void
    {
        $this->stack[] = [
            self::KEY_COROUTINE => Coroutine::create($iter),
            self::KEY_RESOLVER  => $then ?? $this->emptyCb(),
        ];
    }

    /**
     * @return \Closure
     */
    private function emptyCb(): \Closure
    {
        return (static fn() => null);
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        if ($this->running) {
            throw new \LogicException('Cannot rewind a generator that was already run');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        $current = $this->top();

        if ($current === null) {
            return null;
        }

        return $current->current();
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        $current = $this->top();

        if ($current === null) {
            return null;
        }

        return $current->key();
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        $current = $this->top();

        if ($current === null) {
            return false;
        }

        return $current->valid() || \count($this->stack) > 1;
    }

    /**
     * {@inheritDoc}
     */
    public function getReturn()
    {
        $current = $this->top();

        if ($current === null) {
            return null;
        }

        return $current->getReturn();
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        $this->send(null);
    }

    /**
     * {@inheritDoc}
     */
    public function send($value)
    {
        if (\count($this->stack) === 0) {
            return null;
        }

        $this->running = true;
        $current = $this->top();

        if ($current === null) {
            return null;
        }

        try {
            return $current->send($value);
        } finally {
            if (! $current->valid()) {
                $this->pop();
            }
        }
    }

    /**
     * @return void
     */
    private function pop(): void
    {
        [$coroutine, $callback] = \end($this->stack);

        if (\count($this->stack) > 1) {
            try {
                \array_pop($this->stack);
            } finally {
                $callback($coroutine->getReturn());
            }

            return;
        }

        $callback($coroutine->getReturn());
        // Avoid callback evaluation duplication
        $this->stack[\array_key_last($this->stack)][self::KEY_RESOLVER] = $this->emptyCb();
    }

    /**
     * {@inheritDoc}
     */
    public function throw(\Throwable $exception)
    {
        $current = $this->top();

        if ($current === null) {
            throw $exception;
        }

        return $current->throw($exception);
    }
}
