<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes\SearchAttributeKey;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SearchAttributes\ValueType;

/**
 * @psalm-immutable
 */
final class FloatValue extends SearchAttributeKey
{
    public function valueSet(float $value): SearchAttributeUpdate
    {
        return $this->prepareValueSet($value);
    }

    public function getType(): ValueType
    {
        return ValueType::Float;
    }
}
