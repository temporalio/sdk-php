<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use Google\Protobuf\Duration;
use Google\Protobuf\Timestamp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Worker\Core\ProtoTime;

final class ProtoTimeTestCase extends TestCase
{
    private const INTERVAL_CACHE_SIZE = 64;

    public static function provideOptionalDurations(): iterable
    {
        yield 'zero nanoseconds' => [0, null];
        yield 'missing' => [null, null];
        yield 'nanoseconds' => [1_500_000_000, [1, 500_000_000]];
        yield 'array' => [['seconds' => 2, 'nanos' => 3], [2, 3]];
        yield 'empty array' => [[], [0, 0]];
    }

    #[DataProvider('provideOptionalDurations')]
    public function testOptionalDuration(int|array|null $value, ?array $expected): void
    {
        $duration = ProtoTime::optionalDuration($value);

        self::assertSame($expected, $duration === null ? null : [(int) $duration->getSeconds(), $duration->getNanos()]);
    }

    public function testIntervalsAreCachedClones(): void
    {
        $interval = ProtoTime::interval(new Duration(['seconds' => 5]));
        $interval->addSeconds(1);

        self::assertSame(5.0, ProtoTime::interval(new Duration(['seconds' => 5]))->totalSeconds);
        self::assertSame(0.0, ProtoTime::interval(null)->totalSeconds);
    }

    public function testIntervalCacheIsResetWhenFull(): void
    {
        for ($seconds = 1; $seconds <= self::INTERVAL_CACHE_SIZE + 1; ++$seconds) {
            self::assertSame((float) $seconds, ProtoTime::interval(new Duration(['seconds' => $seconds]))->totalSeconds);
        }
    }

    public function testMicroseconds(): void
    {
        $before = (int) (new \DateTimeImmutable())->format('Uu');

        self::assertGreaterThanOrEqual($before, ProtoTime::micros(null));
        self::assertSame(2_000_003, ProtoTime::micros(new Timestamp(['seconds' => 2, 'nanos' => 3_999])));
        self::assertSame(1_500_000, ProtoTime::microsOf(new Duration(['seconds' => 1, 'nanos' => 500_000_000])));
        self::assertSame(0, ProtoTime::microsOf(null));
        self::assertSame('1970-01-01T00:00:02.000003+00:00', ProtoTime::utc(2_000_003)->format('Y-m-d\TH:i:s.uP'));
    }

    public function testDateTimes(): void
    {
        $zone = new \DateTimeZone('Europe/Berlin');
        $time = ProtoTime::dateTime(ProtoTime::timestamp(new \DateTimeImmutable('@1700000000')), $zone);

        self::assertSame([1_700_000_000, 'Europe/Berlin'], [$time->getTimestamp(), $time->getTimezone()->getName()]);
        self::assertSame('Europe/Berlin', ProtoTime::dateTime(null, $zone)->getTimezone()->getName());
    }
}
