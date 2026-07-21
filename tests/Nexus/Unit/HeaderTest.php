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
        self::assertSame('Content-Type', Header::CONTENT_TYPE);
        self::assertSame('application/json', Header::CONTENT_TYPE_JSON);
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
        yield 'unknown unit us' => ['30us'];
        yield 'no unit'         => ['30'];
        yield 'bare number'     => ['5'];
        yield 'only unit'       => ['ms'];
        yield 'pure garbage'    => ['abc'];
        yield 'trailing junk'   => ['12x'];
    }

    #[DataProvider('malformedTimeoutProvider')]
    public function testParseTimeoutRejectsMalformed(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid Nexus timeout/');

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

}
