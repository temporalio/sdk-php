<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalArray;

/**
 * StructuredCalendarSpec describes an event specification relative to the
 * calendar, in a form that's easy to work with programmatically. Each field can
 * be one or more ranges.
 * A timestamp matches if at least one range of each field matches the
 * corresponding fields of the timestamp, except for year: if year is missing,
 * that means all years match. For all fields besides year, at least one Range
 * must be present to match anything.
 *
 * @see \Temporal\Api\Schedule\V1\StructuredCalendarSpec
 */
final class StructuredCalendarSpec
{
    /**
     * Match seconds (0-59)
     */
    #[MarshalArray(name: 'second', of:  Range::class)]
    public readonly array $second;

    /**
     * Match minutes (0-59)
     */
    #[MarshalArray(name: 'minute', of:  Range::class)]
    public readonly array $minute;

    /**
     * Match hours (0-23)
     */
    #[MarshalArray(name: 'hour', of:  Range::class)]
    public readonly array $hour;

    /**
     * Match days of the month (1-31)
     */
    #[MarshalArray(name: 'day_of_month', of:  Range::class)]
    public readonly array $dayOfMonth;

    /**
     * Match months (1-12)
     */
    #[MarshalArray(name: 'month', of:  Range::class)]
    public readonly array $month;

    /**
     * Match years.
     */
    #[MarshalArray(name: 'year', of:  Range::class)]
    public readonly array $year;

    /**
     * Match days of the week (0-6; 0 is Sunday).
     */
    #[MarshalArray(name: 'day_of_week', of:  Range::class)]
    public readonly array $dayOfWeek;

    /**
     * Free-form comment describing the intention of this spec.
     */
    #[Marshal(name: 'comment')]
    public readonly string $comment;
}
