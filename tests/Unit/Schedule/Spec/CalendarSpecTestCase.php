<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule\Spec;

use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Spec\CalendarSpec;

/**
 * @covers \Temporal\Client\Schedule\Spec\CalendarSpec
 */
class CalendarSpecTestCase extends TestCase
{
    public function testWithSecondString(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withSecond('1,2,3');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->second, 'default value was not changed');
        $this->assertSame('1,2,3', $new->second);
    }

    public function testWithSecondInt(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withSecond(45);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->second, 'default value was not changed');
        $this->assertSame('45', $new->second);
    }

    public function testWithMinuteString(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withMinute('1,2,3');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->minute, 'default value was not changed');
        $this->assertSame('1,2,3', $new->minute);
    }

    public function testWithMinuteInt(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withMinute(45);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->minute, 'default value was not changed');
        $this->assertSame('45', $new->minute);
    }

    public function testWithHourString(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withHour('1,2,3');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->hour, 'default value was not changed');
        $this->assertSame('1,2,3', $new->hour);
    }

    public function testWithHourInt(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withHour(12);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->hour, 'default value was not changed');
        $this->assertSame('12', $new->hour);
    }

    public function testWithDayOfMonthString(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withDayOfMonth('1,2,3');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->dayOfMonth, 'default value was not changed');
        $this->assertSame('1,2,3', $new->dayOfMonth);
    }

    public function testWithDayOfMonthInt(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withDayOfMonth(15);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->dayOfMonth, 'default value was not changed');
        $this->assertSame('15', $new->dayOfMonth);
    }

    public function testWithMonthString(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withMonth('1,2,3');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->month, 'default value was not changed');
        $this->assertSame('1,2,3', $new->month);
    }

    public function testWithMonthInt(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withMonth(6);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->month, 'default value was not changed');
        $this->assertSame('6', $new->month);
    }

    public function testWithYearString(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withYear('2020-2028');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->year, 'default value was not changed');
        $this->assertSame('2020-2028', $new->year);
    }

    public function testWithYearInt(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withYear(2024);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->year, 'default value was not changed');
        $this->assertSame('2024', $new->year);
    }

    public function testWithDayOfWeekString(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withDayOfWeek('1,2,3');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->dayOfWeek, 'default value was not changed');
        $this->assertSame('1,2,3', $new->dayOfWeek);
    }

    public function testWithDayOfWeekInt(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withDayOfWeek(5);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('*', $init->dayOfWeek, 'default value was not changed');
        $this->assertSame('5', $new->dayOfWeek);
    }

    public function testWithComment(): void
    {
        $init = CalendarSpec::new();

        $new = $init->withComment('foo');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('', $init->comment, 'default value was not changed');
        $this->assertSame('foo', $new->comment);
    }
}
