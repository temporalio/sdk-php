<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use Temporal\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Internal\Traits\CloneWith;

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
    use CloneWith;

    /**
     * @param string $second Expression to match seconds.
     * @param string $minute Expression to match minutes.
     * @param string $hour Expression to match hours.
     * @param string $dayOfMonth Expression to match days of the month.
     * @param string $month Expression to match months.
     * @param string $year Expression to match years.
     * @param string $dayOfWeek Expression to match days of the week.
     * @param string $comment Free-form comment describing the intention of this spec.
     */
    private function __construct(
        #[MarshalArray(name: 'second', of: Range::class)]
        public readonly string $second,
        #[MarshalArray(name: 'minute', of: Range::class)]
        public readonly string $minute,
        #[MarshalArray(name: 'hour', of: Range::class)]
        public readonly string $hour,
        #[MarshalArray(name: 'day_of_month', of: Range::class)]
        public readonly string $dayOfMonth,
        #[MarshalArray(name: 'month', of: Range::class)]
        public readonly string $month,
        #[MarshalArray(name: 'year', of: Range::class)]
        public readonly string $year,
        #[MarshalArray(name: 'day_of_week', of: Range::class)]
        public readonly string $dayOfWeek,
        #[MarshalArray(name: 'comment', of: Range::class)]
        public readonly string $comment,
    ) {
    }


    /**
     * @param string $second Expression to match seconds.
     * @param string $minute Expression to match minutes.
     * @param string $hour Expression to match hours.
     * @param string $dayOfMonth Expression to match days of the month.
     * @param string $month Expression to match months.
     * @param string $year Expression to match years.
     * @param string $dayOfWeek Expression to match days of the week.
     * @param string $comment Free-form comment describing the intention of this spec.
     */
    public static function new(
        string $second = '*',
        string $minute = '*',
        string $hour = '*',
        string $dayOfMonth = '*',
        string $month = '*',
        string $year = '*',
        string $dayOfWeek = '*',
        string $comment = '',
    ): self {
        return new self($second, $minute, $hour, $dayOfMonth, $month, $year, $dayOfWeek, $comment);
    }

    public function withSecond(string $second): self
    {
        return $this->with('second', $second);
    }

    public function withMinute(string $minute): self
    {
        return $this->with('minute', $minute);
    }

    public function withHour(string $hour): self
    {
        return $this->with('hour', $hour);
    }

    public function withDayOfMonth(string $dayOfMonth): self
    {
        return $this->with('dayOfMonth', $dayOfMonth);
    }

    public function withMonth(string $month): self
    {
        return $this->with('month', $month);
    }

    public function withYear(string $year): self
    {
        return $this->with('year', $year);
    }

    public function withDayOfWeek(string $dayOfWeek): self
    {
        return $this->with('dayOfWeek', $dayOfWeek);
    }

    public function withComment(string $comment): self
    {
        return $this->with('comment', $comment);
    }
}
