<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes\SearchAttributeKey;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SearchAttributes\ValueType;

/**
 * @psalm-immutable
 */
final class StringValue extends SearchAttributeKey
{
    public function valueSet(string|\Stringable $value): SearchAttributeUpdate
    {
        return $this->prepareValueSet((string) $value);
    }

    public function getType(): ValueType
    {
        return ValueType::String;
    }
}
