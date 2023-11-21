<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use DateInterval;
use DateTimeInterface;
use Google\Protobuf\Timestamp;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Internal\Marshaller\Meta\MarshalDateTime;

/**
 *  ScheduleSpec is a complete description of a set of absolute timestamps
 *  (possibly infinite) that an action should occur at. The meaning of a
 *  ScheduleSpec depends only on its contents and never changes, except that the
 *  definition of a time zone can change over time (most commonly, when daylight
 *  saving time policy changes for an area). To create a totally self-contained
 *  ScheduleSpec, use UTC or include timezone_data.
 *  For input, you can provide zero or more of: structured_calendar, calendar,
 *  cron_string, interval, and exclude_structured_calendar, and all of them will
 *  be used (the schedule will take action at the union of all of their times,
 *  minus the ones that match exclude_structured_calendar).
 *  On input, calendar and cron_string fields will be compiled into
 *  structured_calendar (and maybe interval and timezone_name), so if you
 *  Describe a schedule, you'll see only structured_calendar, interval, etc.
 *
 * @see \Temporal\Api\Schedule\V1\ScheduleSpec
 */
final class ScheduleSpec
{
    /**
     * Calendar-based specifications of times.
     *
     * @var list<StructuredCalendarSpec>
     */
    #[MarshalArray(name: 'structured_calendar', of: StructuredCalendarSpec::class)]
    public readonly array $structuredCalendar;

    /**
     * cron_string holds a traditional cron specification as a string. It
     *  accepts 5, 6, or 7 fields, separated by spaces, and interprets them the
     *  same way as CalendarSpec.
     *
     * @var list<non-empty-string>
     */
    #[MarshalArray(name: 'cron_string')]
    public readonly array $cronString;

    /**
     * Calendar-based specifications of times.
     *
     * @var list<CalendarSpec>
     */
    #[MarshalArray(name: 'calendar')]
    public readonly array $calendar;

    /**
     * Interval-based specifications of times.
     *
     * @var list<IntervalSpec>
     */
    #[MarshalArray(name: 'interval', of: DateInterval::class)]
    public readonly array $interval;

    /**
     * Any timestamps matching any of exclude* will be skipped.
     *
     * @var list<CalendarSpec>
     */
    #[MarshalArray(name: 'exclude_calendar', of: CalendarSpec::class)]
    public readonly array $excludeCalendar;

    /**
     * Any timestamps matching any of exclude* will be skipped.
     *
     * @var list<StructuredCalendarSpec>
     */
    #[MarshalArray(name: 'exclude_structured_calendar', of: StructuredCalendarSpec::class)]
    public readonly array $excludeStructuredCalendar;

    /**
     * If start_time is set, any timestamps before start_time will be skipped.
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
    #[Marshal(name: 'jitter', nullable: true)]
    public readonly ?DateInterval $jitter;

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
     * updating timezone_data when the definition changes.
     * Calendar spec matching is based on literal matching of the clock time
     * with no special handling of DST: if you write a calendar spec that fires
     * at 2:30am and specify a time zone that follows DST, that action will not
     * be triggered on the day that has no 2:30am. Similarly, an action that
     * fires at 1:30am will be triggered twice on the day that has two 1:30s.
     * Also note that no actions are taken on leap-seconds (e.g. 23:59:60 UTC).
     */
    #[Marshal(name: 'timezone_name')]
    public readonly string $timezoneName;

    #[Marshal(name: 'timezone_data')]
    public readonly string $timezoneData;
}
