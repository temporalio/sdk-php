<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule\Spec;

use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Spec\CalendarSpec;
use Temporal\Client\Schedule\Spec\IntervalSpec;
use Temporal\Client\Schedule\Spec\Range;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\StructuredCalendarSpec;

/**
 * @covers \Temporal\Client\Schedule\Spec\ScheduleSpec
 */
class ScheduleSpecTestCase extends TestCase
{
    public function testWithTimezoneName(): void
    {
        $init = ScheduleSpec::new();

        $new = $init->withTimezoneName('UTC');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('', $init->timezoneName, 'init value was not changed');
        $this->assertSame('UTC', $new->timezoneName);
    }

    public function testWithTimezoneData(): void
    {
        $init = ScheduleSpec::new();

        $new = $init->withTimezoneData('+01:00');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('', $init->timezoneData, 'init value was not changed');
        $this->assertSame('+01:00', $new->timezoneData);
    }

    /**
     * @dataProvider provideStartEndTime
     */
    public function testWithStartTime(
        mixed $withValue,
        ?string $expectedValue,
        mixed $initValue = null,
        ?string $expectedInitValue = null
    ): void {
        $init = ScheduleSpec::new();
        $init === null or $init = $init->withStartTime($initValue);

        $new = $init->withStartTime($withValue);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($expectedInitValue, $init->startTime?->format(\DateTimeInterface::ATOM), 'init value was not changed');
        $this->assertSame($expectedValue, $new->startTime?->format(\DateTimeInterface::ATOM));
    }

    /**
     * @dataProvider provideStartEndTime
     */
    public function testWithEndTime(
        mixed $withValue,
        ?string $expectedValue,
        mixed $initValue = null,
        ?string $expectedInitValue = null
    ): void {
        $init = ScheduleSpec::new();
        $init === null or $init = $init->withStartTime($initValue);

        $new = $init->withStartTime($withValue);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($expectedInitValue, $init->startTime?->format(\DateTimeInterface::ATOM), 'init value was not changed');
        $this->assertSame($expectedValue, $new->startTime?->format(\DateTimeInterface::ATOM));
    }

    public static function provideStartEndTime(): iterable
    {
        yield 'string' => ['2024-10-01T00:00:00Z', '2024-10-01T00:00:00+00:00'];
        yield 'datetime' => [new \DateTimeImmutable('2024-10-01T00:00:00Z'), '2024-10-01T00:00:00+00:00'];
        yield 'unset' => [null, null, '2024-10-01T00:00:00Z', '2024-10-01T00:00:00+00:00'];
    }

    /**
     * @dataProvider provideJitter
     */
    public function testWithJitter(
        mixed $withValue,
        string $expectedValue,
        mixed $initValue = null,
        string $expectedInitValue = '0/0/0/0/0/0'
    ): void {
        $init = ScheduleSpec::new();
        $init === null or $init = $init->withJitter($initValue);

        $new = $init->withJitter($withValue);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($expectedInitValue, $init->jitter->format('%y/%m/%d/%h/%i/%s'), 'init value was not changed');
        $this->assertSame($expectedValue, $new->jitter->format('%y/%m/%d/%h/%i/%s'));
    }

    public static function provideJitter(): iterable
    {
        yield 'string' => ['10m', '0/0/0/0/10/0'];
        yield 'int' => [10, '0/0/0/0/0/10'];
        yield 'interval' => [new \DateInterval('PT10M'), '0/0/0/0/10/0'];
        yield 'null' => [null, '0/0/0/0/0/0', '10m', '0/0/0/0/10/0'];
    }

    public function testWithCalendarList(): void
    {
        $init = ScheduleSpec::new();
        $calendars =[
            CalendarSpec::new()->withSecond(6)->withMinute('*/6'),
            CalendarSpec::new()->withSecond(6)->withMinute('*/5'),
        ];

        $new = $init->withCalendarList(...$calendars);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->calendarList, 'init value was not changed');
        $this->assertSame($calendars, $new->calendarList);
    }

    public function testWithCalendarListUnset(): void
    {
        $calendars =[
            CalendarSpec::new()->withSecond(6)->withMinute('*/6'),
            CalendarSpec::new()->withSecond(6)->withMinute('*/5'),
        ];
        $init = ScheduleSpec::new()->withCalendarList(...$calendars);

        $new = $init->withCalendarList();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($calendars, $init->calendarList, 'init value was not changed');
        $this->assertSame([], $new->calendarList);
    }

    public function testWithAddedCalendar(): void
    {
        $init = ScheduleSpec::new()->withCalendarList(
            CalendarSpec::new()->withSecond(6)->withMinute('*/6')
        );

        $new = $init->withAddedCalendar(
            CalendarSpec::new()->withSecond(6)->withMinute('*/5')
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->calendarList);
        $this->assertSame('*/6', $init->calendarList[0]->minute);
        $this->assertCount(2, $new->calendarList);
        $this->assertSame('*/6', $new->calendarList[0]->minute);
        $this->assertSame('*/5', $new->calendarList[1]->minute);
    }

    public function testWithCronStringList(): void
    {
        $init = ScheduleSpec::new();

        $new = $init->withCronStringList('0 12 * * 5', new class implements \Stringable {
            public function __toString(): string
            {
                return '0 12 * * 1';
            }
        });

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->cronStringList, 'init value was not changed');
        $this->assertSame(['0 12 * * 5', '0 12 * * 1'], $new->cronStringList);
    }

    public function testWithCronStringListUnset(): void
    {
        $init = ScheduleSpec::new()->withCronStringList('0 12 * * 5', '0 12 * * 1');

        $new = $init->withCronStringList();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(['0 12 * * 5', '0 12 * * 1'], $init->cronStringList, 'init value was not changed');
        $this->assertSame([], $new->cronStringList);
    }

    public function testWithAddedCronString(): void
    {
        $init = ScheduleSpec::new()->withCronStringList('0 12 * * 5');

        $new = $init->withAddedCronString('0 12 * * 1')
            ->withAddedCronString(new class implements \Stringable {
                public function __toString(): string
                {
                    return '0 12 * * 2';
                }
            });

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(['0 12 * * 5'], $init->cronStringList, 'init value was not changed');
        $this->assertSame(['0 12 * * 5', '0 12 * * 1', '0 12 * * 2'], $new->cronStringList);
    }

    public function testWithIntervalList(): void
    {
        $init = ScheduleSpec::new();

        $new = $init->withIntervalList('P2Y', 5, new \DateInterval('PT3M'), IntervalSpec::new(5));

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->intervalList, 'init value was not changed');
        $this->assertSame('2/0/0/0/0/0', $new->intervalList[0]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/0/5', $new->intervalList[1]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/3/0', $new->intervalList[2]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/0/5', $new->intervalList[3]->interval->format('%y/%m/%d/%h/%i/%s'));
    }

    public function testWithIntervalListUnset(): void
    {
        $init = ScheduleSpec::new()->withIntervalList('P2Y', 5, new \DateInterval('PT3M'), IntervalSpec::new(6));

        $new = $init->withIntervalList();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(4, $init->intervalList);
        $this->assertSame('2/0/0/0/0/0', $init->intervalList[0]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/0/5', $init->intervalList[1]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/3/0', $init->intervalList[2]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/0/6', $init->intervalList[3]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame([], $new->intervalList);
    }

    public function testWithAddedInterval(): void
    {
        $init = ScheduleSpec::new()->withIntervalList('P2Y');

        $new = $init
            ->withAddedInterval('P3Y')
            ->withAddedInterval(5)
            ->withAddedInterval(new \DateInterval('PT3M'))
            ->withAddedInterval(IntervalSpec::new(6));

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->intervalList);
        $this->assertSame('2/0/0/0/0/0', $init->intervalList[0]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertCount(5, $new->intervalList);
        $this->assertSame('2/0/0/0/0/0', $new->intervalList[0]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('3/0/0/0/0/0', $new->intervalList[1]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/0/5', $new->intervalList[2]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/3/0', $new->intervalList[3]->interval->format('%y/%m/%d/%h/%i/%s'));
        $this->assertSame('0/0/0/0/0/6', $new->intervalList[4]->interval->format('%y/%m/%d/%h/%i/%s'));
    }

    public function testWithStructuredCalendarList(): void
    {
        $init = ScheduleSpec::new();
        $values = [
            StructuredCalendarSpec::new()->withHours(Range::new(1, 12, 2)),
            StructuredCalendarSpec::new()->withDaysOfWeek(Range::new(1, 5)),
        ];

        $new = $init->withStructuredCalendarList(...$values);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame([], $init->structuredCalendarList, 'init value was not changed');
        $this->assertSame($values, $new->structuredCalendarList);
    }

    public function testWithStructuredCalendarListUnset(): void
    {
        $values = [
            StructuredCalendarSpec::new()->withHours(Range::new(1, 12, 2)),
            StructuredCalendarSpec::new()->withDaysOfWeek(Range::new(1, 5)),
        ];
        $init = ScheduleSpec::new()->withStructuredCalendarList(...$values);

        $new = $init->withStructuredCalendarList();

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame($values, $init->structuredCalendarList, 'init value was not changed');
        $this->assertSame([], $new->structuredCalendarList);
    }

    public function testWithAddedStructuredCalendar(): void
    {
        $init = ScheduleSpec::new()->withStructuredCalendarList(
            StructuredCalendarSpec::new()->withHours($r1 = Range::new(1, 12, 2))
        );

        $new = $init->withAddedStructuredCalendar(
            StructuredCalendarSpec::new()->withDaysOfWeek($r2 = Range::new(1, 5))
        );

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertCount(1, $init->structuredCalendarList);
        $this->assertSame([$r1], $init->structuredCalendarList[0]->hours);
        $this->assertCount(2, $new->structuredCalendarList);
        $this->assertSame([$r1], $new->structuredCalendarList[0]->hours);
        $this->assertSame([$r2], $new->structuredCalendarList[1]->daysOfWeek);
    }
}
