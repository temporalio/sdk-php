<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes;

use Temporal\Common\SearchAttributes\SearchAttributeUpdate\ValueSet;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate\ValueUnset;

/**
 * @psalm-immutable
 */
abstract class SearchAttributeUpdate
{
    /**
     * @param non-empty-string $key
     */
    protected function __construct(
        public readonly string $key,
        public readonly ValueType $type,
    ) {}

    /**
     * @param non-empty-string $key
     */
    public static function valueSet(string $key, ValueType $type, mixed $value): self
    {
        return new ValueSet($key, $type, $value);
    }

    /**
     * @param non-empty-string $key
     */
    public static function valueUnset(string $key, ValueType $type): self
    {
        return new ValueUnset($key, $type);
    }
}
