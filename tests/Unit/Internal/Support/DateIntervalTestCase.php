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
    #[DataProvider('provideValuesToParse')]
    public function testDecode(mixed $value, string $format, int $microseconds): void
    {
        $i = DateInterval::parse($value, $format);

        $this->assertSame($microseconds, (int)$i->totalMicroseconds);
    }

    public static function provideValuesToParse(): iterable
    {
        yield [1, DateInterval::FORMAT_MICROSECONDS, 1];
        yield [1, DateInterval::FORMAT_MILLISECONDS, 1_000];
        yield [1, DateInterval::FORMAT_SECONDS, 1_000_000];
        yield [0.2, DateInterval::FORMAT_SECONDS, 200_000];
        yield [1.2, DateInterval::FORMAT_SECONDS, 1_200_000];
        yield [1.8, DateInterval::FORMAT_SECONDS, 1_800_000];
        yield [1, DateInterval::FORMAT_MINUTES, 60_000_000];
        yield [1.5, DateInterval::FORMAT_MINUTES, 90_000_000];
        yield [1, DateInterval::FORMAT_HOURS, 3_600_000_000];
        yield [1, DateInterval::FORMAT_DAYS, 86_400_000_000];
        yield [1, DateInterval::FORMAT_WEEKS, 604_800_000_000];
    }
}
