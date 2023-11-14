<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

/**
 * Range represents a set of integer values, used to match fields of a calendar
 * time in StructuredCalendarSpec. If end < start, then end is interpreted as
 * equal to start. This means you can use a Range with start set to a value, and
 * end and step unset (defaulting to 0) to represent a single value.
 *
 * @see \Temporal\Api\Schedule\V1\Range
 */
final class Range
{
    /**
     * Start of range (inclusive).
     */
    public readonly int $start;

    /**
     * End of range (inclusive).
     */
    public readonly int $end;

    /**
     * Step (optional, default 1).
     */
    public readonly int $step;
}
