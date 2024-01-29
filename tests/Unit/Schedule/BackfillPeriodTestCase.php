<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\BackfillPeriod;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;

#[CoversClass(\Temporal\Client\Schedule\BackfillPeriod::class)]
class BackfillPeriodTestCase extends TestCase
{
    public function testCreateFromDatetimeImmutable(): void
    {
        $period = BackfillPeriod::new(
            \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, '2021-01-01T00:00:00+00:00'),
            \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T00:00:00+00:00'),
            ScheduleOverlapPolicy::CancelOther,
        );

        $this->assertSame('2021-01-01T00:00:00+00:00', $period->startTime->format(\DateTimeInterface::ATOM));
        $this->assertSame('2021-01-02T00:00:00+00:00', $period->endTime->format(\DateTimeInterface::ATOM));
        $this->assertSame(ScheduleOverlapPolicy::CancelOther, $period->overlapPolicy);
    }

    public function testCreateFromString(): void
    {
        $period = BackfillPeriod::new(
            '2021-01-01T00:00:00+00:00',
            '2021-01-02T00:00:00+00:00',
            ScheduleOverlapPolicy::Unspecified,
        );

        $this->assertSame('2021-01-01T00:00:00+00:00', $period->startTime->format(\DateTimeInterface::ATOM));
        $this->assertSame('2021-01-02T00:00:00+00:00', $period->endTime->format(\DateTimeInterface::ATOM));
        $this->assertSame(ScheduleOverlapPolicy::Unspecified, $period->overlapPolicy);
    }

    public function testWithStartTimeString(): void
    {
        $init = BackfillPeriod::new('2021-01-01T00:00:00+00:00', '2021-01-02T00:00:00+00:00');

        $period = $init->withStartTime('2021-05-01T00:00:00+00:00');

        $this->assertNotSame($init, $period);
        $this->assertSame('2021-05-01T00:00:00+00:00', $period->startTime->format(\DateTimeInterface::ATOM));
        $this->assertSame('2021-01-02T00:00:00+00:00', $period->endTime->format(\DateTimeInterface::ATOM));
        $this->assertSame(ScheduleOverlapPolicy::Unspecified, $period->overlapPolicy);
    }

    public function testWithStartTimeDatetimeImmutable(): void
    {
        $init = BackfillPeriod::new('2021-01-01T00:00:00+00:00', '2021-01-02T00:00:00+00:00');

        $period = $init->withStartTime(
            $dto = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, '2021-05-01T00:00:00+00:00'),
        );

        $this->assertNotSame($init, $period);
        $this->assertSame($dto, $period->startTime);
        $this->assertSame('2021-01-02T00:00:00+00:00', $period->endTime->format(\DateTimeInterface::ATOM));
        $this->assertSame(ScheduleOverlapPolicy::Unspecified, $period->overlapPolicy);
    }

    public function testWithEndTimeString(): void
    {
        $init = BackfillPeriod::new('2021-01-01T00:00:00+00:00', '2021-01-02T00:00:00+00:00');

        $period = $init->withEndTime('2021-05-01T00:00:00+00:00');

        $this->assertNotSame($init, $period);
        $this->assertSame('2021-01-01T00:00:00+00:00', $period->startTime->format(\DateTimeInterface::ATOM));
        $this->assertSame('2021-05-01T00:00:00+00:00', $period->endTime->format(\DateTimeInterface::ATOM));
        $this->assertSame(ScheduleOverlapPolicy::Unspecified, $period->overlapPolicy);
    }

    public function testWithEndTimeDatetimeImmutable(): void
    {
        $init = BackfillPeriod::new('2021-01-01T00:00:00+00:00', '2021-01-02T00:00:00+00:00');

        $period = $init->withEndTime(
            $dto = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, '2021-05-01T00:00:00+00:00'),
        );

        $this->assertNotSame($init, $period);
        $this->assertSame('2021-01-01T00:00:00+00:00', $period->startTime->format(\DateTimeInterface::ATOM));
        $this->assertSame($dto, $period->endTime);
        $this->assertSame(ScheduleOverlapPolicy::Unspecified, $period->overlapPolicy);
    }

    public function testWithOverlapPolicy(): void
    {
        $init = BackfillPeriod::new('2021-01-01T00:00:00+00:00', '2021-01-02T00:00:00+00:00');

        $period = $init->withOverlapPolicy(ScheduleOverlapPolicy::CancelOther);

        $this->assertNotSame($init, $period);
        $this->assertSame(ScheduleOverlapPolicy::Unspecified, $init->overlapPolicy, 'init overlap policy');
        $this->assertSame('2021-01-01T00:00:00+00:00', $period->startTime->format(\DateTimeInterface::ATOM));
        $this->assertSame('2021-01-02T00:00:00+00:00', $period->endTime->format(\DateTimeInterface::ATOM));
        $this->assertSame(ScheduleOverlapPolicy::CancelOther, $period->overlapPolicy);
    }
}
