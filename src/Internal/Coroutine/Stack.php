<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Coroutine;

class Stack implements AppendableInterface, \OuterIterator
{
    /**
     * @var int
     */
    public const KEY_COROUTINE = 0x00;

    /**
     * @var int
     */
    public const KEY_ON_COMPLETE = 0x01;

    /**
     * @var array { 0: CoroutineInterface, 1: \Closure }
     */
    private array $stack = [];

    /**
     * @var \Generator|null
     */
    private ?\Generator $current = null;

    /**
     * @param iterable $iterator
     * @param \Closure|null $then
     */
    public function __construct(iterable $iterator = [], \Closure $then = null)
    {
        if ($iterator !== [] || $then !== null) {
            $this->push($iterator, $then);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        $current = $this->iterator();

        return $current->current();
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        $current = $this->iterator();

        $current->next();
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        $current = $this->iterator();

        return $current->key();
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        $current = $this->iterator();

        return $current->valid();
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        $current = $this->iterator();

        $current->rewind();
    }

    /**
     * {@inheritDoc}
     */
    public function getReturn()
    {
        $current = $this->iterator();

        return $current->getReturn();
    }

    /**
     * {@inheritDoc}
     */
    public function send($value)
    {
        $current = $this->iterator();

        return $current->send($value);
    }

    /**
     * {@inheritDoc}
     */
    public function throw(\Throwable $exception)
    {
        $current = $this->iterator();

        return $current->throw($exception);
    }

    /**
     * @param iterable $iterator
     * @param \Closure|null $then
     */
    public function push(iterable $iterator, \Closure $then = null): void
    {
        $this->stack[] = [
            self::KEY_COROUTINE => Coroutine::create($iterator),
            self::KEY_ON_COMPLETE => $then ?? (static fn() => null),
        ];
    }

    public function getInnerIterator(): \Generator
    {
        $result = null;

        while ($this->stack) {
            /** @var \Generator $coroutine */
            [$coroutine] = \end($this->stack);

            if ($coroutine->valid()) {
                try {
                    $coroutine->send(
                        yield $coroutine->key() => $coroutine->current()
                    );
                } catch (\Throwable $e) {
                    $coroutine->throw($e);
                }
            } else {
                [$last, $then] = \array_pop($this->stack);

                $then($result = $last->getReturn());
            }
        }

        return $result;
    }

    /**
     * @throws \Exception
     */
    private function iterator(): \Generator
    {
        return $this->current ??= $this->getInnerIterator();
    }
}
