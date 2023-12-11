<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule\Spec;

use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Spec\CalendarSpec;
use Temporal\Client\Schedule\Spec\Range;
use Temporal\Client\Schedule\Spec\StructuredCalendarSpec;

/**
 * @covers \Temporal\Client\Schedule\Spec\StructuredCalendarSpec
 */
class StructuredCalendarSpecTest extends TestCase
{
    public function testWithSeconds(): void
    {
        $init = StructuredCalendarSpec::new();
        $values = [
            Range::new(1, 60, 5),
            Range::new(1, 60, 6),
        ];

        $new = $init->withSeconds(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->seconds, 'init value was not changed');
        $this->assertSame($values, $new->seconds);
    }

    public function testWithSecondsUnset(): void
    {
        $values = [
            Range::new(1, 60, 5),
            Range::new(1, 60, 6),
        ];
        $init = StructuredCalendarSpec::new()->withSeconds(...$values);

        $new = $init->withSeconds();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($values, $init->seconds, 'init value was not changed');
        $this->assertSame([], $new->seconds);
    }

    public function testWithAddedSecond(): void
    {
        $init = StructuredCalendarSpec::new()->withSeconds(
            $r1 = Range::new(1, 60, 5)
        );

        $new = $init->withAddedSecond(
            $r2 = Range::new(1, 60, 6)
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->seconds);
        $this->assertSame($r1, $init->seconds[0]);
        $this->assertCount(2, $new->seconds);
        $this->assertSame($r1, $new->seconds[0]);
        $this->assertSame($r2, $new->seconds[1]);
    }

    public function testWithMinutes(): void
    {
        $init = StructuredCalendarSpec::new();
        $values = [
            Range::new(1, 60, 5),
            Range::new(1, 60, 6),
        ];

        $new = $init->withMinutes(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->minutes, 'init value was not changed');
        $this->assertSame($values, $new->minutes);
    }

    public function testWithMinutesUnset(): void
    {
        $values = [
            Range::new(1, 60, 5),
            Range::new(1, 60, 6),
        ];
        $init = StructuredCalendarSpec::new()->withMinutes(...$values);

        $new = $init->withMinutes();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($values, $init->minutes, 'init value was not changed');
        $this->assertSame([], $new->minutes);
    }

    public function testWithAddedMinute(): void
    {
        $init = StructuredCalendarSpec::new()->withMinutes(
            $r1 = Range::new(1, 60, 5)
        );

        $new = $init->withAddedMinute(
            $r2 = Range::new(1, 60, 6)
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->minutes);
        $this->assertSame($r1, $init->minutes[0]);
        $this->assertCount(2, $new->minutes);
        $this->assertSame($r1, $new->minutes[0]);
        $this->assertSame($r2, $new->minutes[1]);
    }

    public function testWithHours(): void
    {
        $init = StructuredCalendarSpec::new();
        $values = [
            Range::new(1, 24, 3),
            Range::new(1, 24, 2),
        ];

        $new = $init->withHours(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->hours, 'init value was not changed');
        $this->assertSame($values, $new->hours);
    }

    public function testWithHoursUnset(): void
    {
        $values = [
            Range::new(1, 24, 3),
            Range::new(1, 24, 2),
        ];
        $init = StructuredCalendarSpec::new()->withHours(...$values);

        $new = $init->withHours();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($values, $init->hours, 'init value was not changed');
        $this->assertSame([], $new->hours);
    }

    public function testWithAddedHour(): void
    {
        $init = StructuredCalendarSpec::new()->withHours(
            $r1 = Range::new(1, 24, 3)
        );

        $new = $init->withAddedHour(
            $r2 = Range::new(1, 24, 2)
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->hours);
        $this->assertSame($r1, $init->hours[0]);
        $this->assertCount(2, $new->hours);
        $this->assertSame($r1, $new->hours[0]);
        $this->assertSame($r2, $new->hours[1]);
    }

    public function testWithDaysOfMonth(): void
    {
        $init = StructuredCalendarSpec::new();
        $values = [
            Range::new(1, 31, 3),
            Range::new(1, 31, 2),
        ];

        $new = $init->withDaysOfMonth(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->daysOfMonth, 'init value was not changed');
        $this->assertSame($values, $new->daysOfMonth);
    }

    public function testWithDaysOfMonthUnset(): void
    {
        $values = [
            Range::new(1, 31, 3),
            Range::new(1, 31, 2),
        ];
        $init = StructuredCalendarSpec::new()->withDaysOfMonth(...$values);

        $new = $init->withDaysOfMonth();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($values, $init->daysOfMonth, 'init value was not changed');
        $this->assertSame([], $new->daysOfMonth);
    }

    public function testWithAddedDayOfMonth(): void
    {
        $init = StructuredCalendarSpec::new()->withDaysOfMonth(
            $r1 = Range::new(1, 31, 3)
        );

        $new = $init->withAddedDayOfMonth(
            $r2 = Range::new(1, 31, 2)
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->daysOfMonth);
        $this->assertSame($r1, $init->daysOfMonth[0]);
        $this->assertCount(2, $new->daysOfMonth);
        $this->assertSame($r1, $new->daysOfMonth[0]);
        $this->assertSame($r2, $new->daysOfMonth[1]);
    }

    public function testWithMonths(): void
    {
        $init = StructuredCalendarSpec::new();
        $values = [
            Range::new(1, 12, 3),
            Range::new(1, 12, 2),
        ];

        $new = $init->withMonths(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->months, 'init value was not changed');
        $this->assertSame($values, $new->months);
    }

    public function testWithMonthsUnset(): void
    {
        $values = [
            Range::new(1, 12, 3),
            Range::new(1, 12, 2),
        ];
        $init = StructuredCalendarSpec::new()->withMonths(...$values);

        $new = $init->withMonths();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($values, $init->months, 'init value was not changed');
        $this->assertSame([], $new->months);
    }

    public function testWithAddedMonth(): void
    {
        $init = StructuredCalendarSpec::new()->withMonths(
            $r1 = Range::new(1, 12, 3)
        );

        $new = $init->withAddedMonth(
            $r2 = Range::new(1, 12, 2)
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->months);
        $this->assertSame($r1, $init->months[0]);
        $this->assertCount(2, $new->months);
        $this->assertSame($r1, $new->months[0]);
        $this->assertSame($r2, $new->months[1]);
    }

    public function testWithDaysOfWeek(): void
    {
        $init = StructuredCalendarSpec::new();
        $values = [
            Range::new(1, 7, 3),
            Range::new(1, 7, 2),
        ];

        $new = $init->withDaysOfWeek(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->daysOfWeek, 'init value was not changed');
        $this->assertSame($values, $new->daysOfWeek);
    }

    public function testWithDaysOfWeekUnset(): void
    {
        $values = [
            Range::new(1, 7, 3),
            Range::new(1, 7, 2),
        ];
        $init = StructuredCalendarSpec::new()->withDaysOfWeek(...$values);

        $new = $init->withDaysOfWeek();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($values, $init->daysOfWeek, 'init value was not changed');
        $this->assertSame([], $new->daysOfWeek);
    }

    public function testWithAddedDayOfWeek(): void
    {
        $init = StructuredCalendarSpec::new()->withDaysOfWeek(
            $r1 = Range::new(1, 7, 3)
        );

        $new = $init->withAddedDayOfWeek(
            $r2 = Range::new(1, 7, 2)
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->daysOfWeek);
        $this->assertSame($r1, $init->daysOfWeek[0]);
        $this->assertCount(2, $new->daysOfWeek);
        $this->assertSame($r1, $new->daysOfWeek[0]);
        $this->assertSame($r2, $new->daysOfWeek[1]);
    }

    public function testWithYears(): void
    {
        $init = StructuredCalendarSpec::new();
        $values = [
            Range::new(2021, 2042, 3),
            Range::new(2021, 2042, 2),
        ];

        $new = $init->withYears(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->years, 'init value was not changed');
        $this->assertSame($values, $new->years);
    }

    public function testWithYearsUnset(): void
    {
        $values = [
            Range::new(2021, 2042, 3),
            Range::new(2021, 2042, 2),
        ];
        $init = StructuredCalendarSpec::new()->withYears(...$values);

        $new = $init->withYears();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($values, $init->years, 'init value was not changed');
        $this->assertSame([], $new->years);
    }

    public function testWithAddedYear(): void
    {
        $init = StructuredCalendarSpec::new()->withYears(
            $r1 = Range::new(2021, 2042, 3)
        );

        $new = $init->withAddedYear(
            $r2 = Range::new(2021, 2042, 2)
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->years);
        $this->assertSame($r1, $init->years[0]);
        $this->assertCount(2, $new->years);
        $this->assertSame($r1, $new->years[0]);
        $this->assertSame($r2, $new->years[1]);
    }

    public function testWithComment(): void
    {
        $init = CalendarSpec::new();
        $new = $init->withComment('test comment');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('', $init->comment, 'init value was not changed');
        $this->assertSame('test comment', $new->comment);
    }

    public function testWithoutComment(): void
    {
        $init = CalendarSpec::new()->withComment('test comment');
        $new = $init->withComment('');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('test comment', $init->comment, 'init value was not changed');
        $this->assertSame('', $new->comment);
    }
}
