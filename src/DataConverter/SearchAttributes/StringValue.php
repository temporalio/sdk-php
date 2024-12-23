<?php

declare(strict_types=1);

namespace Temporal\DataConverter\SearchAttributes;

/**
 * @template-extends SearchAttributeKey<string>
 * @psalm-immutable
 */
final class StringValue extends SearchAttributeKey
{
    protected function getType(): string
    {
        return ValueType::String->value;
    }
}
