<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Internal\Support\DateInterval;

/**
 * The periodic timeout while the activity is in execution.
 *
 * This is the max interval the server needs to hear at least one ping from the activity.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class HeartbeatTimeout
{
    public readonly ?\DateInterval $interval;

    public function __construct(
        \DateInterval|string|int|null $timeout,
    ) {
        $this->interval = DateInterval::parseOrNull($timeout, DateInterval::FORMAT_SECONDS);
    }
}
