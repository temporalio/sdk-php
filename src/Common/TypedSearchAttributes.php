<?php

declare(strict_types=1);

namespace Temporal\Common;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\SearchAttributes\ValueType;

/**
 * @implements \IteratorAggregate<SearchAttributeKey, mixed>
 */
class TypedSearchAttributes implements \IteratorAggregate, \Countable
{
    /**
     * @param null|\SplObjectStorage<SearchAttributeKey, mixed> $collection
     */
    private function __construct(
        private readonly ?\SplObjectStorage $collection = null,
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Create a new instance from the provided collection of untyped Search Attributes.
     *
     * @param array<non-empty-string, mixed> $array
     */
    public static function fromCollection(array $array): self
    {
        $result = self::empty();
        foreach ($array as $name => $value) {
            $result = $result->withUntypedValue($name, $value);
        }

        return $result;
    }

    /**
     * @param array<non-empty-string, array{value: string, type: non-empty-string}> $array
     *
     * ```php
     *  [
     *      "foo" => [
     *          "value" => "bar",
     *          "type" => "keyword",
     *      ],
     *  ]
     * ```
     *
     * @internal
     */
    public static function fromJsonArray(array $array): self
    {
        if ($array === []) {
            return self::empty();
        }

        $collection = new \SplObjectStorage();
        foreach ($array as $name => ['type' => $type, 'value' => $value]) {
            try {
                $vt = ValueType::from($type);
                $key = SearchAttributeKey::for($vt, $name);
                $collection->offsetSet($key, $key->valueSet($value)->value);
            } catch (\Throwable) {
                // Ignore invalid values.
            }
        }

        return new self($collection);
    }

    /**
     * @throws \LogicException If key type mismatch.
     */
    public function get(SearchAttributeKey $key): mixed
    {
        $found = $this->getKeyByName($key->getName());

        return match (true) {
            $found === null => null,
            $found->getType() === $key->getType() => $this->collection[$found],
            default => throw new \LogicException('Search Attribute type mismatch.'),
        };
    }

    public function hasKey(SearchAttributeKey $key): bool
    {
        return $this->getKeyByName($key->getName()) !== null;
    }

    public function withValue(SearchAttributeKey $key, mixed $value): self
    {
        $collection = $this->collection === null
            ? new \SplObjectStorage()
            : clone $this->collection;
        $collection->offsetSet($key, $value);

        return new self($collection);
    }

    /**
     * @param non-empty-string $name
     *
     * @throws \InvalidArgumentException If the value type is not supported.
     */
    public function withUntypedValue(string $name, mixed $value): self
    {
        return match (true) {
            \is_bool($value) => $this->withValue(SearchAttributeKey::forBool($name), $value),
            \is_int($value) => $this->withValue(SearchAttributeKey::forInteger($name), $value),
            \is_float($value) => $this->withValue(SearchAttributeKey::forFloat($name), $value),
            \is_array($value) => $this->withValue(SearchAttributeKey::forKeywordList($name), $value),
            $value instanceof \Stringable,
            \is_string($value) => $this->withValue(SearchAttributeKey::forString($name), $value),
            $value instanceof \DateTimeInterface => $this->withValue(SearchAttributeKey::forDatetime($name), $value),
            default => throw new \InvalidArgumentException('Unsupported value type.'),
        };
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        $count = (int) $this->collection?->count();
        \assert($count >= 0);
        return $count;
    }

    /**
     * @return \Traversable<SearchAttributeKey, mixed>
     */
    public function getIterator(): \Traversable
    {
        if ($this->collection === null) {
            return;
        }

        foreach ($this->collection as $key) {
            yield $key => $this->collection[$key];
        }
    }

    /**
     * Get the value associated with the Search Attribute name.
     *
     * @param non-empty-string $name
     */
    public function offsetGet(string $name): mixed
    {
        $key = $this->getKeyByName($name);

        return $key === null ? null : $this->collection[$key];
    }

    /**
     * @param non-empty-string $name
     */
    private function getKeyByName(string $name): ?SearchAttributeKey
    {
        foreach ($this->collection ?? [] as $item) {
            if ($item->getName() === $name) {
                return $item;
            }
        }

        return null;
    }
}
