<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Internal\Traits\CloneWith;

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
    use CloneWith;

    /**
     * Match seconds (0-59)
     */
    #[MarshalArray(name: 'second', of: Range::class)]
    public readonly array $seconds;

    /**
     * Match minutes (0-59)
     */
    #[MarshalArray(name: 'minute', of: Range::class)]
    public readonly array $minutes;

    /**
     * Match hours (0-23)
     */
    #[MarshalArray(name: 'hour', of: Range::class)]
    public readonly array $hours;

    /**
     * Match days of the month (1-31)
     */
    #[MarshalArray(name: 'day_of_month', of: Range::class)]
    public readonly array $daysOfMonth;

    /**
     * Match months (1-12)
     */
    #[MarshalArray(name: 'month', of: Range::class)]
    public readonly array $months;

    /**
     * Match years.
     */
    #[MarshalArray(name: 'year', of: Range::class)]
    public readonly array $years;

    /**
     * Match days of the week (0-6; 0 is Sunday).
     */
    #[MarshalArray(name: 'day_of_week', of: Range::class)]
    public readonly array $daysOfWeek;

    /**
     * Free-form comment describing the intention of this spec.
     */
    #[Marshal(name: 'comment')]
    public readonly string $comment;

    private function __construct()
    {
        $this->seconds = [];
        $this->minutes = [];
        $this->hours = [];
        $this->daysOfMonth = [];
        $this->months = [];
        $this->years = [];
        $this->daysOfWeek = [];
        $this->comment = '';
    }

    public static function new(): self
    {
        return new self();
    }

    public function withSeconds(Range ...$seconds): self
    {
        return $this->cloneWith('seconds', $seconds);
    }

    public function withAddedSecond(Range $second): self
    {
        $value = $this->seconds;
        $value[] = $second;
        return $this->cloneWith('seconds', $value);
    }

    public function withMinutes(Range ...$minutes): self
    {
        return $this->cloneWith('minutes', $minutes);
    }

    public function withAddedMinute(Range $minute): self
    {
        $value = $this->minutes;
        $value[] = $minute;
        return $this->cloneWith('minutes', $value);
    }

    public function withHours(Range ...$hours): self
    {
        return $this->cloneWith('hours', $hours);
    }

    public function withAddedHour(Range $hour): self
    {
        $value = $this->hours;
        $value[] = $hour;
        return $this->cloneWith('hours', $value);
    }

    public function withDaysOfMonth(Range ...$daysOfMonth): self
    {
        return $this->cloneWith('daysOfMonth', $daysOfMonth);
    }

    public function withAddedDayOfMonth(Range $dayOfMonth): self
    {
        $value = $this->daysOfMonth;
        $value[] = $dayOfMonth;
        return $this->cloneWith('daysOfMonth', $value);
    }

    public function withMonths(Range ...$months): self
    {
        return $this->cloneWith('months', $months);
    }

    public function withAddedMonth(Range $month): self
    {
        $value = $this->months;
        $value[] = $month;
        return $this->cloneWith('months', $value);
    }

    public function withYears(Range ...$years): self
    {
        return $this->cloneWith('years', $years);
    }

    public function withAddedYear(Range $year): self
    {
        $value = $this->years;
        $value[] = $year;
        return $this->cloneWith('years', $value);
    }

    public function withDaysOfWeek(Range ...$daysOfWeek): self
    {
        return $this->cloneWith('daysOfWeek', $daysOfWeek);
    }

    public function withAddedDayOfWeek(Range $dayOfWeek): self
    {
        $value = $this->daysOfWeek;
        $value[] = $dayOfWeek;
        return $this->cloneWith('daysOfWeek', $value);
    }

    public function withComment(string $comment): self
    {
        return $this->cloneWith('comment', $comment);
    }
}
