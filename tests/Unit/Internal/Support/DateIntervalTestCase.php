<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Support\DateInterval;

#[CoversClass(DateInterval::class)]
final class DateIntervalTestCase extends TestCase
{
    public static function provideValuesToParse(): iterable
    {
        yield [1, DateInterval::FORMAT_MICROSECONDS, 1, '0/0/0/0'];
        yield [1, DateInterval::FORMAT_MILLISECONDS, 1_000, '0/0/0/0'];
        yield [0.25, DateInterval::FORMAT_SECONDS, 250_000, '0/0/0/0'];
        yield [1, DateInterval::FORMAT_SECONDS, 1_000_000, '0/0/0/1'];
        yield [1.25, DateInterval::FORMAT_SECONDS, 1_250_000, '0/0/0/1'];
        yield [1.8, DateInterval::FORMAT_SECONDS, 1_800_000, '0/0/0/1'];
        yield [1, DateInterval::FORMAT_MINUTES, 60_000_000, '0/0/1/0'];
        yield [1.5, DateInterval::FORMAT_MINUTES, 90_000_000, '0/0/1/30'];
        yield [1, DateInterval::FORMAT_HOURS, 3_600_000_000, '0/1/0/0'];
        yield [1, DateInterval::FORMAT_DAYS, 86_400_000_000, '1/0/0/0'];
        yield [1, DateInterval::FORMAT_WEEKS, 604_800_000_000, '7/0/0/0'];
        yield [(0.1 + 0.7) * 10.0, DateInterval::FORMAT_SECONDS, 8_000_000, '0/0/0/8'];
        yield [(0.1 + 0.7) * 10.0, DateInterval::FORMAT_DAYS, 691200000000, '8/0/0/0'];
        yield [(0.1 + 0.7) * 10.0, DateInterval::FORMAT_WEEKS, 4838400000000, '56/0/0/0'];
        yield [null, DateInterval::FORMAT_MILLISECONDS, 0, '0/0/0/0'];
    }

    #[DataProvider('provideValuesToParse')]
    public function testParse(mixed $value, string $format, int $microseconds, string $formatted): void
    {
        $i = DateInterval::parse($value, $format);

        self::assertSame($microseconds, (int) $i->totalMicroseconds);
        self::assertSame($formatted, $i->format('%d/%h/%i/%s'));
        if ($i->totalMicroseconds > 1_000_000) {
            self::assertGreaterThan(0, $i->totalSeconds);
        }
    }

    public function testParseAndFormat(): void
    {
        $i = DateInterval::parse(6_200, DateInterval::FORMAT_MILLISECONDS);

        $this->assertSame(6_200_000, (int) $i->totalMicroseconds);
        self::assertSame('0/0/0/6', $i->format('%y/%h/%i/%s'));
    }

    public function testParseFromDuration(): void
    {
        $duration = (new \Google\Protobuf\Duration())
            ->setSeconds(5124)
            ->setNanos(123456000);

        $i = DateInterval::parse($duration);

        self::assertSame(5124, (int) $i->totalSeconds);
        self::assertSame(123_456, $i->microseconds);
    }
}
