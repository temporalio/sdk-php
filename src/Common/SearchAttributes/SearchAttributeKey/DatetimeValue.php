<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes\SearchAttributeKey;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SearchAttributes\ValueType;

/**
 * @psalm-immutable
 */
final class DatetimeValue extends SearchAttributeKey
{
    /**
     * @param non-empty-string|\DateTimeInterface $value
     */
    public function valueSet(string|\DateTimeInterface $value): SearchAttributeUpdate
    {
        return $this->prepareValueSet(match (true) {
            \is_string($value) => new \DateTimeImmutable($value),
            $value instanceof \DateTimeImmutable => $value,
            default => \DateTimeImmutable::createFromInterface($value),
        });
    }

    public function getType(): ValueType
    {
        return ValueType::Datetime;
    }
}
