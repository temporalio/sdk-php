<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Unit\Handler;

use Temporal\Nexus\Handler\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testNowReturnsUtcMoment(): void
    {
        $clock = new SystemClock();
        $now = $clock->now();
        self::assertSame('UTC', $now->getTimezone()->getName());
    }

    public function testTwoCallsAreMonotonic(): void
    {
        $clock = new SystemClock();
        $a = $clock->now();
        $b = $clock->now();
        self::assertGreaterThanOrEqual($a, $b);
    }
}
