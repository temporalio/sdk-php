<?php

declare(strict_types=1);

namespace Temporal\DataConverter\SearchAttributes;

/**
 * @template-extends SearchAttributeKey<bool>
 * @psalm-immutable
 */
final class BoolValue extends SearchAttributeKey
{
    protected function getType(): string
    {
        return ValueType::Bool->value;
    }
}
