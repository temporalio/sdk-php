<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Internal\Support\DateInterval;
use Temporal\Workflow;

/**
 * Options for {@see Workflow::awaitWithTimeout()}.
 *
 * ```php
 *  yield Workflow::awaitWithTimeout(
 *      AwaitOptions::new(30)->withTimerOptions(
 *          TimerOptions::new()->withSummary('rtds-resolution-wait'),
 *      ),
 *      fn(): bool => $this->resolution !== null,
 *  );
 * ```
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 */
final class AwaitOptions
{
    public function __construct(
        /**
         * Await timeout.
         */
        public readonly \DateInterval $interval,

        /**
         * Options set for the underlying timer created.
         */
        public readonly ?TimerOptions $options = null,
    ) {}

    /**
     * @param DateIntervalValue $interval Await timeout.
     */
    public static function new(mixed $interval, ?TimerOptions $options = null): self
    {
        return new self(DateInterval::parse($interval, DateInterval::FORMAT_SECONDS), $options);
    }

    /**
     * Await timeout.
     *
     * @param DateIntervalValue $interval
     */
    public function withInterval(mixed $interval): self
    {
        return new self(DateInterval::parse($interval, DateInterval::FORMAT_SECONDS), $this->options);
    }

    /**
     * Options set for the underlying timer created.
     */
    public function withTimerOptions(?TimerOptions $options): self
    {
        return new self($this->interval, $options);
    }
}
