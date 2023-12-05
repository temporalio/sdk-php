<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use Temporal\Internal\Marshaller\Meta\Marshal;
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
        #[Marshal(name: 'second')]
        public readonly string $second,
        #[Marshal(name: 'minute')]
        public readonly string $minute,
        #[Marshal(name: 'hour')]
        public readonly string $hour,
        #[Marshal(name: 'day_of_month')]
        public readonly string $dayOfMonth,
        #[Marshal(name: 'month')]
        public readonly string $month,
        #[Marshal(name: 'year')]
        public readonly string $year,
        #[Marshal(name: 'day_of_week')]
        public readonly string $dayOfWeek,
        #[Marshal(name: 'comment')]
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

    public function withSecond(string|int $second): self
    {
        return $this->with('second', (string)$second);
    }

    public function withMinute(string|int $minute): self
    {
        return $this->with('minute', (string)$minute);
    }

    public function withHour(string|int $hour): self
    {
        return $this->with('hour', (string)$hour);
    }

    public function withDayOfMonth(string|int $dayOfMonth): self
    {
        return $this->with('dayOfMonth', (string)$dayOfMonth);
    }

    public function withMonth(string|int $month): self
    {
        return $this->with('month', (string)$month);
    }

    public function withYear(string|int $year): self
    {
        return $this->with('year', (string)$year);
    }

    public function withDayOfWeek(string|int $dayOfWeek): self
    {
        return $this->with('dayOfWeek', (string)$dayOfWeek);
    }

    public function withComment(string $comment): self
    {
        /** @see self::$comment */
        return $this->with('comment', $comment);
    }
}
