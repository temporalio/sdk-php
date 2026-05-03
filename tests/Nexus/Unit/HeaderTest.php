<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Header;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Header::class)]
final class HeaderTest extends TestCase
{
    public function testAllKnownNexusHeaderConstants(): void
    {
        // Guard against typos in constant values — these are wire-level and must be stable.
        self::assertSame('Request-Timeout', Header::REQUEST_TIMEOUT);
        self::assertSame('Operation-Timeout', Header::OPERATION_TIMEOUT);
        self::assertSame('Nexus-Operation-Id', Header::OPERATION_ID);
        self::assertSame('Nexus-Operation-Token', Header::OPERATION_TOKEN);
        self::assertSame('Nexus-Operation-Start-Time', Header::OPERATION_START_TIME);
        self::assertSame('Nexus-Operation-Close-Time', Header::OPERATION_CLOSE_TIME);
        self::assertSame('Nexus-Operation-State', Header::OPERATION_STATE);
        self::assertSame('Nexus-Request-Id', Header::REQUEST_ID);
        self::assertSame('Nexus-Link', Header::LINK);
        self::assertSame('Nexus-Callback-Url', Header::CALLBACK_URL);
        self::assertSame('Nexus-Callback-Token', Header::CALLBACK_TOKEN);
        self::assertSame('Nexus-Callback-', Header::CALLBACK_PREFIX);
        self::assertSame('Nexus-Request-Retryable', Header::RETRYABLE);
        self::assertSame('Content-Type', Header::CONTENT_TYPE);
        self::assertSame('application/json', Header::CONTENT_TYPE_JSON);
    }

    public function testHeaderCannotBeInstantiated(): void
    {
        $reflection = new \ReflectionClass(Header::class);
        $ctor = $reflection->getConstructor();

        self::assertNotNull($ctor);
        self::assertTrue($ctor->isPrivate(), 'Header is a static namespace; must not be instantiable');
    }

    // ── get() lookup ────────────────────────────────────────────────

    public function testGetLooksUpByCaseInsensitiveName(): void
    {
        $headers = ['nexus-operation-token' => 'abc123']; // already lowercase

        self::assertSame('abc123', Header::get($headers, Header::OPERATION_TOKEN));
        self::assertSame('abc123', Header::get($headers, 'NEXUS-OPERATION-TOKEN'));
        self::assertSame('abc123', Header::get($headers, 'nexus-operation-token'));
        self::assertNull(Header::get($headers, 'x-unknown'));
    }

    public function testGetReturnsNullOnEmptyBag(): void
    {
        self::assertNull(Header::get([], Header::REQUEST_ID));
    }

    // ── parseTimeout() ──────────────────────────────────────────────

    public function testParseTimeoutMilliseconds(): void
    {
        $i = Header::parseTimeout('250ms');

        self::assertNotNull($i);
        // DateInterval from 'milliseconds' carries the fraction in ->f.
        self::assertEqualsWithDelta(0.25, $i->s + $i->f, 0.001);
    }

    public function testParseTimeoutSeconds(): void
    {
        $i = Header::parseTimeout('30s');

        self::assertNotNull($i);
        self::assertSame(30, $i->s);
    }

    public function testParseTimeoutMinutes(): void
    {
        $i = Header::parseTimeout('2m');

        self::assertNotNull($i);
        self::assertSame(2, $i->i);
    }

    public function testParseTimeoutReturnsNullForEmpty(): void
    {
        self::assertNull(Header::parseTimeout(''));
        self::assertNull(Header::parseTimeout('   '));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function malformedTimeoutProvider(): iterable
    {
        yield 'fractional'      => ['0.5s'];
        yield 'negative'        => ['-30s'];
        yield 'unknown unit h'  => ['30h'];
        yield 'unknown unit us' => ['30us'];
        yield 'no unit'         => ['30'];
        yield 'only unit'       => ['ms'];
        yield 'embedded space'  => ['30 s'];
        yield 'leading plus'    => ['+30s'];
    }

    #[DataProvider('malformedTimeoutProvider')]
    public function testParseTimeoutRejectsMalformed(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid Nexus timeout format/');

        Header::parseTimeout($input);
    }

    // ── deadlineFromTimeout() ───────────────────────────────────────

    public function testDeadlineFromTimeoutAddsIntervalToNow(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        $deadline = Header::deadlineFromTimeout('30s', $now);

        self::assertNotNull($deadline);
        self::assertSame('2026-01-01T00:00:30+00:00', $deadline->format('c'));
    }

    public function testDeadlineFromTimeoutReturnsNullForEmpty(): void
    {
        self::assertNull(Header::deadlineFromTimeout(''));
    }

    public function testDeadlineFromTimeoutThrowsOnMalformed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Header::deadlineFromTimeout('garbage');
    }

    // ── formatCloseTime() / parseCloseTime() ─────────────────────────

    public function testFormatCloseTimeRfc3339WithMs(): void
    {
        $t = new \DateTimeImmutable('2026-04-25T12:43:36.123456+00:00');

        self::assertSame('2026-04-25T12:43:36.123Z', Header::formatCloseTime($t));
    }

    public function testFormatCloseTimeNormalizesNonUtcInputToUtc(): void
    {
        $t = new \DateTimeImmutable('2026-04-25T15:43:36.500+03:00');

        self::assertSame('2026-04-25T12:43:36.500Z', Header::formatCloseTime($t));
    }

    public function testFormatCloseTimePadsMillisecondZeros(): void
    {
        $t = new \DateTimeImmutable('2026-04-25T12:00:00+00:00');

        self::assertSame('2026-04-25T12:00:00.000Z', Header::formatCloseTime($t));
    }

    public function testParseCloseTimeAcceptsZForm(): void
    {
        $t = Header::parseCloseTime('2026-04-25T12:43:36.123Z');

        self::assertSame('2026-04-25T12:43:36.123', $t->format('Y-m-d\TH:i:s.v'));
    }

    public function testParseCloseTimeAcceptsOffsetForm(): void
    {
        $t = Header::parseCloseTime('2026-04-25T15:43:36.123+03:00');

        self::assertSame(
            '2026-04-25T12:43:36.123',
            $t->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v'),
        );
    }

    public function testParseCloseTimeAcceptsSecondsOnly(): void
    {
        $t = Header::parseCloseTime('2026-04-25T12:43:36Z');

        self::assertSame('2026-04-25T12:43:36', $t->format('Y-m-d\TH:i:s'));
    }

    public function testParseCloseTimeRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nexus-Operation-Close-Time must not be empty');
        Header::parseCloseTime('   ');
    }

    public function testParseCloseTimeRejectsMalformed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/RFC 3339/');
        Header::parseCloseTime('not-a-timestamp');
    }

    public function testCloseTimeRoundTrip(): void
    {
        $original = new \DateTimeImmutable('2026-04-25T12:43:36.789+00:00');

        $encoded = Header::formatCloseTime($original);
        $parsed = Header::parseCloseTime($encoded);

        self::assertSame($original->getTimestamp(), $parsed->getTimestamp());
        self::assertSame(789000, (int) $parsed->format('u'));
    }

    // ── formatStartTime() / parseStartTime() ─────────────────────────

    public function testFormatStartTimeImfFixdate(): void
    {
        $t = new \DateTimeImmutable('2026-04-25T12:43:36+00:00');

        self::assertSame('Sat, 25 Apr 2026 12:43:36 GMT', Header::formatStartTime($t));
    }

    public function testFormatStartTimeNormalizesNonUtc(): void
    {
        $t = new \DateTimeImmutable('2026-04-25T15:43:36+03:00');

        self::assertSame('Sat, 25 Apr 2026 12:43:36 GMT', Header::formatStartTime($t));
    }

    public function testParseStartTimeImfFixdate(): void
    {
        $t = Header::parseStartTime('Sat, 25 Apr 2026 12:43:36 GMT');

        self::assertSame('2026-04-25T12:43:36+00:00', $t->format('c'));
    }

    public function testParseStartTimeFallsBackForObsoleteForms(): void
    {
        // RFC 850 form — receivers must tolerate per RFC 9110.
        $t = Header::parseStartTime('Saturday, 25-Apr-26 12:43:36 GMT');

        self::assertSame('2026-04-25', $t->format('Y-m-d'));
        self::assertSame('12:43:36', $t->format('H:i:s'));
    }

    public function testParseStartTimeRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nexus-Operation-Start-Time must not be empty');
        Header::parseStartTime('');
    }

    public function testParseStartTimeRejectsGarbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/HTTP-date/');
        Header::parseStartTime('not-a-timestamp');
    }

    public function testStartTimeRoundTrip(): void
    {
        $original = new \DateTimeImmutable('2026-04-25T12:43:36+00:00');

        $encoded = Header::formatStartTime($original);
        $parsed = Header::parseStartTime($encoded);

        self::assertSame($original->getTimestamp(), $parsed->getTimestamp());
    }
}
