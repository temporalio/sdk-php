<?php

declare(strict_types=1);

namespace Temporal\DataConverter\SearchAttributes;

/**
 * @template-extends SearchAttributeKey<int>
 * @psalm-immutable
 */
final class IntValue extends SearchAttributeKey
{
    protected function getType(): string
    {
        return ValueType::Int->value;
    }
}
