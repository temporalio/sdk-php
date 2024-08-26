<?php

declare(strict_types=1);

namespace Temporal\Client\Common;

/**
 * Used to throttle code execution in presence of failures using exponential backoff logic.
 *
 * The formula used to calculate the next sleep interval is:
 *
 * ```
 * jitter = rand(-maxJitterCoefficient, +maxJitterCoefficient)
 * wait = min(pow(backoffCoefficient, failureCount - 1) * initialInterval * (1 + jitter), maxInterval)
 * ```
 *
 * Note
 * `initialInterval` may be changed in runtime depending on the failure type.
 * That it means that attempt X can possibly get a shorter throttle than attempt X-1.
 *
 * Example:
 *
 * ```php
 * $throttler = new BackoffThrottler(maxInterval: 60_000, 0.1, 2.0);
 *
 * // First retry
 * // There 1000 is initial interval for the RESOURCE_EXHAUSTED exception
 * $throttler->calculateSleepTime(failureCount: 1, baseInterval: 1000);
 *
 * // Second retry
 * // There 500 is a common initial interval for all other exceptions
 * $throttler->calculateSleepTime(failureCount: 2, baseInterval: 500);
 * ```
 *
 * @internal
 */
final class BackoffThrottler
{
    /**
     * @param int $maxInterval Maximum sleep interval in milliseconds. Must be greater than 0.
     * @param float $maxJitterCoefficient Maximum jitter to apply. Must be in the range [0.0, 1.0).
     *        0.1 means that actual retry time can be +/- 10% of the calculated time.
     * @param float $backoffCoefficient Coefficient used to calculate the next retry backoff interval.
     *        The next retry interval is previous interval multiplied by this coefficient.
     *        Must be greater than 1.0.
     */
    public function __construct(
        private readonly int $maxInterval,
        private readonly float $maxJitterCoefficient,
        private readonly float $backoffCoefficient,
    ) {
        $maxJitterCoefficient >= 0 && $maxJitterCoefficient < 1 or throw new \InvalidArgumentException(
            '$jitterCoefficient must be in the range [0.0, 1.0).',
        );
        $this->maxInterval > 0 or throw new \InvalidArgumentException('$maxInterval must be greater than 0.');
        $this->backoffCoefficient >= 1.0 or throw new \InvalidArgumentException(
            '$backoffCoefficient must be greater than 1.',
        );
    }

    /**
     * Calculates the next sleep interval in milliseconds.
     *
     * @param int $failureCount number of failures
     * @param int $initialInterval in milliseconds
     *
     * @return int<0, max>
     *
     * @psalm-assert int<1, max> $failureCount
     * @psalm-assert int<1, max> $initialInterval
     *
     * @psalm-suppress InvalidOperand
     */
    public function calculateSleepTime(int $failureCount, int $initialInterval): int
    {
        $failureCount > 0 or throw new \InvalidArgumentException('$failureCount must be greater than 0.');
        $initialInterval > 0 or throw new \InvalidArgumentException('$initialInterval must be greater than 0.');

        // Choose a random number in the range -maxJitterCoefficient ... +maxJitterCoefficient
        $jitter = \random_int(-1000, 1000) * $this->maxJitterCoefficient / 1000;
        $sleepTime = \min(
            $this->backoffCoefficient ** ($failureCount - 1) * $initialInterval * (1.0 + $jitter),
            $this->maxInterval,
        );

        return \abs((int)$sleepTime);
    }
}
