<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes\SearchAttributeKey;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SearchAttributes\ValueType;

/**
 * @psalm-immutable
 */
final class IntValue extends SearchAttributeKey
{
    public function valueSet(int $value): SearchAttributeUpdate
    {
        return $this->prepareValueSet($value);
    }

    protected function getType(): ValueType
    {
        return ValueType::Int;
    }
}
