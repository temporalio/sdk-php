<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity\Attribute;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Internal\Support\DateInterval;

/**
 * The periodic timeout while the activity is in execution.
 *
 * This is the max interval the server needs to hear at least one ping from the activity.
 *
 * @psalm-import-type DateIntervalValue from \Temporal\Internal\Support\DateInterval
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class HeartbeatTimeout
{
    public readonly \DateInterval $interval;

    /**
     * @param DateIntervalValue $timeout
     */
    public function __construct(
        mixed $timeout,
    ) {
        $this->interval = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);

        if ($this->interval->totalMicroseconds < 0) {
            throw new \InvalidArgumentException('Heartbeat timeout must be non-negative.');
        }
    }
}
