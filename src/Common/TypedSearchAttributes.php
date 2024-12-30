<?php

declare(strict_types=1);

namespace Temporal\Common;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\SearchAttributes\ValueType;

class TypedSearchAttributes implements \Countable
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
        $found = $this->findByName($key->getName());

        return match (true) {
            $found === null => null,
            $found->getType() === $key->getType() => $this->collection[$found],
            default => throw new \LogicException('Search Attribute type mismatch.'),
        };
    }

    public function hasKey(SearchAttributeKey $key): bool
    {
        return $this->findByName($key->getName()) !== null;
    }

    public function withValue(SearchAttributeKey $key, mixed $value): self {}

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        $count = (int) $this->collection?->count();
        \assert($count >= 0);
        return $count;
    }

    private function findByName(string $name): ?SearchAttributeKey
    {
        foreach ($this->collection ?? [] as $item) {
            if ($item->getName() === $name) {
                return $item;
            }
        }

        return null;
    }
}
