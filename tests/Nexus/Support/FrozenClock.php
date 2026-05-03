<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Nexus\Support;

use Psr\Clock\ClockInterface;

/**
 * In-memory PSR-20 clock for deterministic deadline tests.
 *
 * `advance()` moves time forward without touching the wall clock; `set()`
 * jumps to an absolute moment. Lets tests exercise deadline-trip paths
 * without `sleep`/`usleep`.
 */
final class FrozenClock implements ClockInterface
{
    public function __construct(
        private \DateTimeImmutable $now = new \DateTimeImmutable('2026-01-01T00:00:00Z'),
    ) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(\DateInterval $interval): void
    {
        $this->now = $this->now->add($interval);
    }

    public function set(\DateTimeImmutable $moment): void
    {
        $this->now = $moment;
    }
}
