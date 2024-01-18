<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use DateTimeInterface;
use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Internal\Marshaller\Meta\MarshalDateTime;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\DateTime;
use Temporal\Internal\Traits\CloneWith;

/**
 *  ScheduleSpec is a complete description of a set of absolute timestamps
 *  (possibly infinite) that an action should occur at. The meaning of a
 *  ScheduleSpec depends only on its contents and never changes, except that the
 *  definition of a time zone can change over time (most commonly, when daylight
 *  saving time policy changes for an area). To create a totally self-contained
 *  ScheduleSpec, use UTC or include {@see self::$timezoneData}.
 *  For input, you can provide zero or more of: structuredCalendar, calendar,
 *  cronString, interval, and exclude_structured_calendar, and all of them will
 *  be used (the schedule will take action at the union of all of their times,
 *  minus the ones that match excludeStructuredCalendar).
 *  On input, calendar and cronString fields will be compiled into
 *  {@see self::structuredCalendarList} (and maybe interval and timezoneName), so if you
 *  Describe a schedule, you'll see only {@see self::$structuredCalendarList},
 *  {@see self::$intervalList}, etc.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 *
 * @see \Temporal\Api\Schedule\V1\ScheduleSpec
 */
final class ScheduleSpec
{
    use CloneWith;

    /**
     * Calendar-based specifications of times.
     *
     * @var list<StructuredCalendarSpec>
     */
    #[MarshalArray(name: 'structured_calendar', of: StructuredCalendarSpec::class)]
    public readonly array $structuredCalendarList;

    /**
     * A cronStringList item holds a traditional cron specification as a string.
     * It accepts 5, 6, or 7 fields, separated by spaces, and interprets them the
     * same way as CalendarSpec.
     *
     * @var list<non-empty-string>
     */
    #[MarshalArray(name: 'cron_string')]
    public readonly array $cronStringList;

    /**
     * Calendar-based specifications of times.
     *
     * @var list<CalendarSpec>
     */
    #[MarshalArray(name: 'calendar', of: CalendarSpec::class)]
    public readonly array $calendarList;

    /**
     * Interval-based specifications of times.
     *
     * @var list<IntervalSpec>
     */
    #[MarshalArray(name: 'interval', of: IntervalSpec::class)]
    public readonly array $intervalList;

    /**
     * Any timestamps matching any of exclude* will be skipped.
     *
     * @var list<CalendarSpec>
     */
    #[MarshalArray(name: 'exclude_calendar', of: CalendarSpec::class)]
    public readonly array $excludeCalendarList;

    /**
     * Any timestamps matching any of exclude* will be skipped.
     *
     * @var list<StructuredCalendarSpec>
     */
    #[MarshalArray(name: 'exclude_structured_calendar', of: StructuredCalendarSpec::class)]
    public readonly array $excludeStructuredCalendarList;

    /**
     * If startTime is set, any timestamps before startTime will be skipped.
     * (Together, startTime and endTime make an inclusive interval.)
     */
    #[MarshalDateTime(name: 'start_time', to: Timestamp::class, nullable: true)]
    public readonly ?DateTimeInterface $startTime;

    /**
     * If endTime is set, any timestamps after endTime will be skipped.
     */
    #[MarshalDateTime(name: 'end_time', to: Timestamp::class, nullable: true)]
    public readonly ?DateTimeInterface $endTime;

    /**
     * All timestamps will be incremented by a random value from 0 to this
     * amount of jitter.
     */
    #[Marshal(name: 'jitter', of: Duration::class)]
    public readonly \DateInterval $jitter;

    /**
     * Time zone to interpret all calendar-based specs in.
     */
    #[Marshal(name: 'timezone_name')]
    public readonly string $timezoneName;

    #[Marshal(name: 'timezone_data')]
    public readonly string $timezoneData;

    private function __construct()
    {
        $this->structuredCalendarList = [];
        $this->cronStringList = [];
        $this->calendarList = [];
        $this->intervalList = [];
        $this->excludeCalendarList = [];
        $this->excludeStructuredCalendarList = [];
        $this->startTime = null;
        $this->endTime = null;
        $this->jitter = new \DateInterval('PT0S');
        $this->timezoneName = '';
        $this->timezoneData = '';
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Returns a new instance with the replaced structured calendar list.
     */
    public function withStructuredCalendarList(StructuredCalendarSpec ...$structuredCalendar): self
    {
        /** @see self::$structuredCalendarList */
        return $this->with('structuredCalendarList', $structuredCalendar);
    }

    /**
     * Calendar-based specifications of times.
     */
    public function withAddedStructuredCalendar(StructuredCalendarSpec $structuredCalendar): self
    {
        $value = $this->structuredCalendarList;
        $value[] = $structuredCalendar;

        /** @see self::$structuredCalendarList */
        return $this->with('structuredCalendarList', $value);
    }

    /**
     * Returns a new instance with the replaced cron string list.
     */
    public function withCronStringList(\Stringable|string ...$cron): self
    {
        /** @see self::$cronStringList */
        return $this->with('cronStringList', \array_map(static fn($item) => (string)$item, $cron));
    }

    /**
     * A traditional cron specification as a string.
     * It accepts 5, 6, or 7 fields, separated by spaces, and interprets them the
     * same way as CalendarSpec.
     *
     * @param \Stringable|non-empty-string $cron
     */
    public function withAddedCronString(\Stringable|string $cron): self
    {
        $value = $this->cronStringList;
        $value[] = (string)$cron;

        /** @see self::$cronStringList */
        return $this->with('cronStringList', $value);
    }

    /**
     * Returns a new instance with the replaced calendar list.
     */
    public function withCalendarList(CalendarSpec ...$calendar): self
    {
        /** @see self::$calendarList */
        return $this->with('calendarList', \array_values($calendar));
    }

    /**
     * Calendar-based specifications of times.
     */
    public function withAddedCalendar(CalendarSpec $calendar): self
    {
        $value = $this->calendarList;
        $value[] = $calendar;

        /** @see self::$calendarList */
        return $this->with('calendarList', $value);
    }

    /**
     * Returns a new instance with the replaced interval list.
     *
     * @param DateIntervalValue|IntervalSpec ...$interval
     */
    public function withIntervalList(mixed ...$interval): self
    {
        foreach ($interval as $key => $item) {
            if ($item instanceof IntervalSpec) {
                $interval[$key] = $item;
                continue;
            }

            $interval[$key] = IntervalSpec::new($item);
        }

        /** @see self::$intervalList */
        return $this->with('intervalList', $interval);
    }

    /**
     * Interval-based specifications of times.
     *
     * @param DateIntervalValue|IntervalSpec $interval
     */
    public function withAddedInterval(mixed $interval): self
    {
        $value = $this->intervalList;
        if ($interval instanceof IntervalSpec) {
            $value[] = $interval;
        } else {
            \assert(DateInterval::assert($interval));
            $value[] = IntervalSpec::new($interval);
        }

        /** @see self::$intervalList */
        return $this->with('intervalList', $value);
    }

    /**
     * Returns a new instance with the replaced exclude calendar list.
     */
    public function withExcludeCalendarList(CalendarSpec ...$calendar): self
    {
        /** @see self::$excludeCalendarList */
        return $this->with('excludeCalendarList', $calendar);
    }

    /**
     * Any timestamps matching any of exclude* will be skipped.
     */
    public function withAddedExcludeCalendar(CalendarSpec $calendar): self
    {
        $value = $this->excludeCalendarList;
        $value[] = $calendar;

        /** @see self::$excludeCalendarList */
        return $this->with('excludeCalendarList', $value);
    }

    /**
     * Returns a new instance with the replaced exclude structured calendar list.
     */
    public function withExcludeStructuredCalendarList(StructuredCalendarSpec ...$structuredCalendar): self
    {
        /** @see self::$excludeStructuredCalendarList */
        return $this->with('excludeStructuredCalendarList', $structuredCalendar);
    }

    /**
     * Any timestamps matching any of exclude* will be skipped.
     */
    public function withAddedExcludeStructuredCalendar(StructuredCalendarSpec $structuredCalendar): self
    {
        $value = $this->excludeStructuredCalendarList;
        $value[] = $structuredCalendar;

        /** @see self::$excludeStructuredCalendarList */
        return $this->with('excludeStructuredCalendarList', $value);
    }

    /**
     * If startTime is set, any timestamps before startTime will be skipped.
     * (Together, startTime and endTime make an inclusive interval.)
     */
    public function withStartTime(DateTimeInterface|string|null $dateTime): self
    {
        /** @see self::$startTime */
        return $this->with('startTime', $dateTime === null ? null : DateTime::parse($dateTime));
    }

    /**
     * If endTime is set, any timestamps after endTime will be skipped.
     */
    public function withEndTime(DateTimeInterface|string|null $dateTime): self
    {
        /** @see self::$endTime */
        return $this->with('endTime', $dateTime === null ? null : DateTime::parse($dateTime));
    }

    /**
     * All timestamps will be incremented by a random value from 0 to this
     * amount of jitter.
     *
     * @param DateIntervalValue|null $interval Int value means seconds
     */
    public function withJitter(mixed $interval): self
    {
        /** @psalm-suppress RiskyTruthyFalsyComparison */
        if (empty($interval)) {
            /** @see self::$jitter */
            return $this->with('jitter', new \DateInterval('PT0S'));
        }

        \assert(DateInterval::assert($interval));
        $interval = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);

        /** @see self::$jitter */
        return $this->with('jitter', $interval);
    }

    /**
     * Time zone to interpret all calendar-based specs in.
     * If unset, defaults to UTC. We recommend using UTC for your application if
     * at all possible, to avoid various surprising properties of time zones.
     * Time zones may be provided by name, corresponding to names in the IANA
     * time zone database (see https://www.iana.org/time-zones). The definition
     * will be loaded by the Temporal server from the environment it runs in.
     * If your application requires more control over the time zone definition
     * used, it may pass in a complete definition in the form of a TZif file
     * from the time zone database. If present, this will be used instead of
     * loading anything from the environment. You are then responsible for
     * updating {@see self::$timezoneData} when the definition changes.
     * Calendar spec matching is based on literal matching of the clock time
     * with no special handling of DST: if you write a calendar spec that fires
     * at 2:30am and specify a time zone that follows DST, that action will not
     * be triggered on the day that has no 2:30am. Similarly, an action that
     * fires at 1:30am will be triggered twice on the day that has two 1:30s.
     * Also note that no actions are taken on leap-seconds (e.g. 23:59:60 UTC).
     */
    public function withTimezoneName(string $timezoneName): self
    {
        /** @see self::$timezoneName */
        return $this->with('timezoneName', $timezoneName);
    }

    public function withTimezoneData(string $timezoneData): self
    {
        /** @see self::$timezoneData */
        return $this->with('timezoneData', $timezoneData);
    }
}
