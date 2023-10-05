<?php

declare(strict_types=1);

namespace Temporal\Internal\Promise;

use ArrayAccess;
use Countable;
use Iterator;
use RuntimeException;
use Traversable;

/**
 * @internal
 * @psalm-internal Temporal
 * @template TKey of array-key
 * @template TValue of Traversable
 * @implements Iterator<TKey, TValue>
 * @implements ArrayAccess<TKey, TValue>
 */
final class Reasons extends RuntimeException implements Iterator, ArrayAccess, Countable
{
    /**
     * @param array<TKey, TValue> $collection
     */
    public function __construct(
        public array $collection,
    ) {
        parent::__construct();
    }

    public function current(): mixed
    {
        return \current($this->collection);
    }

    public function next(): void
    {
        \next($this->collection);
    }

    public function key(): string|int|null
    {
        return \key($this->collection);
    }

    public function valid(): bool
    {
        return null !== \key($this->collection);
    }

    public function rewind(): void
    {
        \reset($this->collection);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->collection[$offset]);
    }

    /**
     * @param TKey $offset
     * @return TValue
     */
    public function offsetGet(mixed $offset): Traversable
    {
        return $this->collection[$offset];
    }

    /**
     * @param TKey $offset
     * @param TValue $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->collection[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->collection[$offset]);
    }

    public function count(): int
    {
        return \count($this->collection);
    }
}
