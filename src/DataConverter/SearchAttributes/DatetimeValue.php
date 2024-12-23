<?php

declare(strict_types=1);

namespace Temporal\DataConverter\SearchAttributes;

use DateTimeImmutable;

/**
 * @template-extends SearchAttributeKey<DateTimeImmutable>
 * @psalm-immutable
 */
final class DatetimeValue extends SearchAttributeKey
{
    public function getValue(): string
    {
        return $this->value->format(\DateTimeImmutable::RFC3339);
    }

    protected function getType(): string
    {
        return ValueType::Datetime->value;
    }
}
