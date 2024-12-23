<?php

declare(strict_types=1);

namespace Temporal\DataConverter\SearchAttributes;

/**
 * @template-extends SearchAttributeKey<list<string>>
 * @psalm-immutable
 */
final class KeywordListValue extends SearchAttributeKey
{
    protected function getType(): string
    {
        return ValueType::KeywordList->value;
    }
}
