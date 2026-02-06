<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Spiral\Attributes\NamedArgumentConstructor;

/**
 * Whether to wait for canceled activity to be completed
 * (activity can be failed, completed, cancel accepted).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class CancellationType
{
    public readonly int $value;

    public function __construct(
        ActivityCancellationType|int $type = ActivityCancellationType::TRY_CANCEL,
    ) {
        $this->value = $type instanceof ActivityCancellationType
            ? $type->value
            : $type;
    }
}
