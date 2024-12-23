<?php

declare(strict_types=1);

namespace Temporal\DataConverter\SearchAttributes;

/**
 * @template-extends SearchAttributeKey<float>
 * @psalm-immutable
 */
final class FloatValue extends SearchAttributeKey
{
    protected function getType(): string
    {
        return ValueType::Float->value;
    }
}
