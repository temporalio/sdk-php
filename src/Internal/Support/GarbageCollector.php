<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

/**
 * Garbage collector that might be called after some number of ticks or after certain timeout.
 * @internal
 */
final class GarbageCollector
{
    /** @var positive-int Last time when GC was called. */
    private int $lastTime;

    /** @var int<0, max> Number of ticks since last GC. */
    private int $counter = 0;

    /**
     * @param positive-int $threshold Number of ticks before GC will be called.
     * @param int<0, max> $timeout Timeout in seconds.
     * @param positive-int|null $lastTime Start point for timeout.
     */
    public function __construct(
        private readonly int $threshold,
        private readonly int $timeout,
        ?int $lastTime = null,
    ) {
        $this->lastTime = $lastTime ?? \time();
    }

    /**
     * Check if GC should be called.
     */
    public function check(): bool
    {
        if (++$this->counter >= $this->threshold) {
            return true;
        }

        if (\time() - $this->lastTime > $this->timeout) {
            return true;
        }

        return false;
    }

    /**
     * Call GC.
     */
    public function collect(): void
    {
        \gc_collect_cycles();

        $this->lastTime = \time();
        $this->counter = 0;
    }
}
