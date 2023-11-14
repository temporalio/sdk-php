<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use Temporal\Internal\Marshaller\Meta\MarshalArray;

/**
 * CalendarSpec describes an event specification relative to the calendar,
 * similar to a traditional cron specification, but with labeled fields. Each
 * field can be one of:
 *   - *: matches always
 *   - x: matches when the field equals x
 *   - x/y: matches when the field equals x+n*y where n is an integer
 *   - x-z: matches when the field is between x and z inclusive
 *   - w,x,y,...: matches when the field is one of the listed values
 * Each x, y, z, ... is either a decimal integer, or a month or day of week name
 * or abbreviation (in the appropriate fields).
 * A timestamp matches if all fields match.
 * Note that fields have different default values, for convenience.
 * Note that the special case that some cron implementations have for treating
 * `dayOfMonth` and `dayOfWeek` as "or" instead of "and" when both are set is
 * not implemented.
 * `dayOfWeek` can accept 0 or 7 as Sunday
 * {@see CalendarSpec} gets compiled into {@see StructuredCalendarSpec}, which is what will be
 * returned if you describe the schedule.
 *
 * @see \Temporal\Api\Schedule\V1\CalendarSpec
 */
final class CalendarSpec
{
    /**
     * Expression to match seconds.
     */
    #[MarshalArray(name: 'second', of: Range::class)]
    public readonly string $second;

    /**
     * Expression to match minutes.
     */
    #[MarshalArray(name: 'minute', of: Range::class)]
    public readonly string $minute;

    /**
     * Expression to match hours.
     */
    #[MarshalArray(name: 'hour', of: Range::class)]
    public readonly string $hour;

    /**
     * Expression to match days of the month.
     */
    #[MarshalArray(name: 'day_of_month', of: Range::class)]
    public readonly string $dayOfMonth;

    /**
     * Expression to match months.
     */
    #[MarshalArray(name: 'month', of: Range::class)]
    public readonly string $month;

    /**
     * Expression to match years.
     */
    #[MarshalArray(name: 'year', of: Range::class)]
    public readonly string $year;

    /**
     * Expression to match days of the week.
     */
    #[MarshalArray(name: 'day_of_week', of: Range::class)]
    public readonly string $dayOfWeek;

    /**
     * Free-form comment describing the intention of this spec.
     */
    #[MarshalArray(name: 'comme', of: Range::class)]
    public readonly string $comme;
}
