<?php

declare(strict_types=1);

namespace Temporal\Internal\Marshaller\Type;

use Temporal\Activity\ActivityCancellationType as Policy;

/**
 * Converts a boolean value to an activity cancellation policy.
 *
 * @see Policy
 * @extends Type<bool>
 * @internal
 */
final class ActivityCancellationType extends Type
{
    public function parse($value, $current): int
    {
        return $value ? Policy::WAIT_CANCELLATION_COMPLETED : Policy::TRY_CANCEL;
    }

    public function serialize($value): bool
    {
        return match ($value) {
            Policy::WAIT_CANCELLATION_COMPLETED => true,
            Policy::TRY_CANCEL => false,
            default => throw new \InvalidArgumentException("Option #{$value} is currently not supported"),
        };
    }
}
