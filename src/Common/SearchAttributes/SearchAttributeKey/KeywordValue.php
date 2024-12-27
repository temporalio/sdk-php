<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes\SearchAttributeKey;

use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SearchAttributes\ValueType;

/**
 * @template-extends SearchAttributeKey<string>
 * @psalm-immutable
 */
final class KeywordValue extends SearchAttributeKey
{
    public function valueSet(string|\Stringable $value): SearchAttributeUpdate
    {
        return $this->prepareValueSet((string) $value);
    }

    protected function getType(): ValueType
    {
        return ValueType::Keyword;
    }
}
