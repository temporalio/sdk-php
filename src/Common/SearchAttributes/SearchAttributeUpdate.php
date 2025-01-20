<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes;

use Temporal\Common\SearchAttributes\SearchAttributeUpdate\ValueSet;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate\ValueUnset;

/**
 * @internal
 * @psalm-immutable
 */
abstract class SearchAttributeUpdate
{
    /**
     * @param non-empty-string $name
     */
    protected function __construct(
        public readonly string $name,
        public readonly ValueType $type,
    ) {}

    /**
     * @param non-empty-string $key
     */
    public static function valueSet(string $key, ValueType $type, mixed $value): ValueSet
    {
        return new ValueSet($key, $type, $value);
    }

    /**
     * @param non-empty-string $key
     */
    public static function valueUnset(string $key, ValueType $type): ValueUnset
    {
        return new ValueUnset($key, $type);
    }
}
