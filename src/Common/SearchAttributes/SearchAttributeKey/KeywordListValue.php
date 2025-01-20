<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes\SearchAttributeKey;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SearchAttributes\ValueType;

/**
 * @psalm-immutable
 */
final class KeywordListValue extends SearchAttributeKey
{
    /**
     * @param iterable<string|\Stringable> $value
     */
    public function valueSet(array $value): SearchAttributeUpdate
    {
        $values = [];
        foreach ($value as $v) {
            $values[] = (string) $v;
        }

        return $this->prepareValueSet($values);
    }

    public function getType(): ValueType
    {
        return ValueType::KeywordList;
    }
}
