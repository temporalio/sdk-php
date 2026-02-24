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

    public static function provideIso8601DurationFormats(): \Generator
    {
        // Valid ISO 8601 duration formats
        yield 'two days' => ['P2D', true];
        yield 'two seconds' => ['PT2S', true];
        yield 'six years five minutes' => ['P6YT5M', true];
        yield 'three months' => ['P3M', true];
        yield 'three minutes' => ['PT3M', true];
        yield 'full format' => ['P1Y2M3DT4H5M6S', true];
        yield 'weeks only' => ['P2W', true];
        yield 'hours and minutes' => ['PT1H30M', true];
        yield 'days and hours' => ['P1DT12H', true];
        yield 'alternative datetime format' => ['P0001-00-00T00:00:00', true];
        yield 'decimal seconds' => ['PT1.5S', true];

        // Invalid formats (Carbon-specific or non-ISO 8601)
        yield 'only period marker' => ['P', false];
        yield 'only period and time marker' => ['PT', false];
        yield 'natural language' => ['2 days', false];
        yield 'human readable' => ['1 hour 30 minutes', false];
        yield 'no period marker' => ['2D', false];
        yield 'wrong order' => ['P4D1Y', false];
        yield 'time without T' => ['P1H30M', false];
        yield 'negative value' => ['P-2D', false];
        yield 'negative prefix' => ['-P2D', false];
        yield 'spaces' => ['P 2 D', false];
        yield 'lowercase' => ['p2d', false];
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

    public function testParseWithFractionalHours(): void
    {
        $i = DateInterval::parse(1.5, DateInterval::FORMAT_HOURS);

        $this->assertSame('0/1/30/0', $i->format('%d/%h/%i/%s'));
    }

    public function testParseWithFractionalDays(): void
    {
        $i = DateInterval::parse(1.5, DateInterval::FORMAT_DAYS);

        $this->assertSame('1/12/0/0', $i->format('%d/%h/%i/%s'));
    }

    public function testParseWithFractionalWeeks(): void
    {
        $i = DateInterval::parse(0.5, DateInterval::FORMAT_WEEKS);

        $this->assertSame('3/12/0/0', $i->format('%d/%h/%i/%s'));
    }

    public function testParseInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unrecognized date time interval format');

        DateInterval::parse(new \stdClass());
    }

    public function testParseInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date interval format');

        DateInterval::parse(100, 'invalid_format');
    }

    public function testToDurationWithNullEmptyAndEmptyInterval(): void
    {
        $interval = new \DateInterval('PT0S');
        $result = DateInterval::toDuration($interval, nullEmpty: true);

        $this->assertNull($result);
    }

    public function testToDurationWithNullEmptyAndNonEmptyInterval(): void
    {
        $interval = new \DateInterval('PT5S');
        $result = DateInterval::toDuration($interval, nullEmpty: true);

        $this->assertNotNull($result);
        $this->assertSame(5, $result->getSeconds());
    }

    public function testToDurationWithNull(): void
    {
        $result = DateInterval::toDuration(null);

        $this->assertNull($result);
    }

    public function testParseOrNullReturnsNullForNull(): void
    {
        $this->assertNull(DateInterval::parseOrNull(null));
    }

    public function testParseOrNullReturnsIntervalForValue(): void
    {
        $result = DateInterval::parseOrNull(5, DateInterval::FORMAT_SECONDS);

        $this->assertInstanceOf(\Carbon\CarbonInterval::class, $result);
    }

    public function testAssertReturnsTrueForString(): void
    {
        $this->assertTrue(DateInterval::assert('PT5S'));
    }

    public function testAssertReturnsTrueForInt(): void
    {
        $this->assertTrue(DateInterval::assert(5));
    }

    public function testAssertReturnsTrueForFloat(): void
    {
        $this->assertTrue(DateInterval::assert(1.5));
    }

    public function testAssertReturnsTrueForDateInterval(): void
    {
        $this->assertTrue(DateInterval::assert(new \DateInterval('PT5S')));
    }

    public function testAssertReturnsFalseForNull(): void
    {
        $this->assertFalse(DateInterval::assert(null));
    }

    public function testAssertReturnsFalseForArray(): void
    {
        $this->assertFalse(DateInterval::assert([]));
    }

    #[DataProvider('provideIso8601DurationFormats')]
    public function testParseDetectsIso8601FormatCorrectly(string $interval, bool $shouldBeIso8601): void
    {
        // Arrange
        $reflection = new \ReflectionClass(DateInterval::class);
        $method = $reflection->getMethod('isIso8601DurationFormat');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke(null, $interval);

        // Assert
        self::assertSame(
            $shouldBeIso8601,
            $result,
            \sprintf(
                'String "%s" should %s recognized as ISO 8601 duration format',
                $interval,
                $shouldBeIso8601 ? 'be' : 'NOT be',
            ),
        );
    }

    public static function provideCarbonDateIntervalDifferences(): \Generator
    {
        // Cases where Carbon and DateInterval parse the same string differently
        // Format: [interval string, expected warning]

        // P2M: Carbon parses as 2 minutes, DateInterval as 2 months
        yield 'P2M - ambiguous months/minutes' => ['P2M', true];

        // Cases that should NOT trigger warning (identical parsing)
        yield 'PT2M - explicit minutes with T' => ['PT2M', false];
        yield 'P1Y - explicit years' => ['P1Y', false];
        yield 'P2D - explicit days' => ['P2D', false];
        yield 'PT5S - explicit seconds' => ['PT5S', false];
    }

    #[DataProvider('provideCarbonDateIntervalDifferences')]
    public function testParseTriggersWarningWhenCarbonAndDateIntervalDiffer(
        string $interval,
        bool $shouldTriggerWarning,
    ): void {
        // Arrange
        $warningTriggered = false;
        $warningMessage = '';

        \set_error_handler(static function (int $errno, string $errstr) use (&$warningTriggered, &$warningMessage): bool {
            if ($errno === \E_USER_WARNING && \str_contains($errstr, 'Ambiguous duration')) {
                $warningTriggered = true;
                $warningMessage = $errstr;
                return true;
            }
            return false;
        });

        // Act
        try {
            $result = DateInterval::parse($interval);
        } finally {
            \restore_error_handler();
        }

        // Assert
        self::assertInstanceOf(\Carbon\CarbonInterval::class, $result);

        if ($shouldTriggerWarning) {
            self::assertTrue(
                $warningTriggered,
                \sprintf('Expected warning for interval "%s" but none was triggered', $interval),
            );
            self::assertStringContainsString(
                'Ambiguous duration',
                $warningMessage,
                'Warning message should mention ambiguous duration',
            );
            self::assertStringContainsString(
                \sprintf('"%s"', $interval),
                $warningMessage,
                'Warning message should contain the interval value',
            );
            self::assertStringContainsString(
                'Carbon and DateInterval parse it differently',
                $warningMessage,
                'Warning message should explain the issue',
            );
        } else {
            self::assertFalse(
                $warningTriggered,
                \sprintf(
                    'Did not expect warning for interval "%s" but one was triggered: %s',
                    $interval,
                    $warningMessage,
                ),
            );
        }
    }

    public static function provideNonIso8601FormatsNoWarning(): \Generator
    {
        // Natural language formats that Carbon accepts but aren't ISO 8601
        // These should NOT trigger warnings because they don't match ISO 8601 format
        yield 'natural language - 2 days' => ['2 days'];
        yield 'natural language - 1 hour' => ['1 hour'];
        yield 'natural language - 30 minutes' => ['30 minutes'];
    }

    #[DataProvider('provideNonIso8601FormatsNoWarning')]
    public function testParseDoesNotTriggerWarningForNonIso8601Formats(string $interval): void
    {
        // Arrange
        $warningTriggered = false;

        \set_error_handler(static function (int $errno, string $errstr) use (&$warningTriggered): bool {
            if ($errno === \E_USER_WARNING && \str_contains($errstr, 'Ambiguous duration')) {
                $warningTriggered = true;
                return true;
            }
            return false;
        });

        // Act
        try {
            $result = DateInterval::parse($interval);
        } finally {
            \restore_error_handler();
        }

        // Assert
        self::assertInstanceOf(\Carbon\CarbonInterval::class, $result);
        self::assertFalse(
            $warningTriggered,
            \sprintf(
                'Non-ISO 8601 format "%s" should not trigger DateInterval comparison warning',
                $interval,
            ),
        );
    }
}
