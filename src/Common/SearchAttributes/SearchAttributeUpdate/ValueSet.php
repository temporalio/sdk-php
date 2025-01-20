<?php

declare(strict_types=1);

namespace Temporal\Common\SearchAttributes\SearchAttributeUpdate;

use Temporal\Common\SearchAttributes\SearchAttributeUpdate;
use Temporal\Common\SearchAttributes\ValueType;

/**
 * @psalm-immutable
 */
final class ValueSet extends SearchAttributeUpdate
{
    /**
     * @param non-empty-string $key
     */
    protected function __construct(
        string $key,
        ValueType $type,
        public readonly mixed $value,
    ) {
        parent::__construct($key, $type);
    }
}
