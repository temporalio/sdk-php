<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Coroutine;

class Decorator implements CoroutineInterface
{
    /**
     * @var \Iterator
     */
    private \Iterator $iterator;

    /**
     * @param iterable $iterable
     */
    public function __construct(iterable $iterable)
    {
        $this->iterator = $this->cast($iterable);
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        $this->iterator->rewind();
    }

    /**
     * @param iterable $iterable
     * @return \Iterator
     */
    private function cast(iterable $iterable): \Iterator
    {
        switch (true) {
            case $iterable instanceof \Iterator:
                return $iterable;

            case $iterable instanceof \Traversable:
                return new \IteratorIterator($iterable);

            case \is_array($iterable):
                return new \ArrayIterator($iterable);

            default:
                throw new \InvalidArgumentException('Unrecognized iterator type ' . \get_debug_type($iterable));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return $this->iterator->current();
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->iterator->key();
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        $this->iterator->next();
    }

    /**
     * {@inheritDoc}
     */
    public function getReturn()
    {
        if ($this->iterator instanceof \Generator || $this->iterator instanceof CoroutineInterface) {
            return $this->iterator->getReturn();
        }

        if ($this->iterator->valid()) {
            throw new \LogicException('Cannot get return value of an iterator that hasn\'t returned');
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function send($value)
    {
        if ($this->iterator instanceof \Generator || $this->iterator instanceof CoroutineInterface) {
            return $this->iterator->send($value);
        }

        $this->next();

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function throw(\Throwable $exception)
    {
        if ($this->iterator instanceof \Generator || $this->iterator instanceof CoroutineInterface) {
            return $this->iterator->throw($exception);
        }

        throw $exception;
    }
}
