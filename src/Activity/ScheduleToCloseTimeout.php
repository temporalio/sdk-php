<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Attribute;
use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Internal\Support\DateInterval;

/**
 * The end to end timeout for the activity.
 *
 * Optional: The default value is the sum of ScheduleToStartTimeout and StartToCloseTimeout.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class ScheduleToCloseTimeout
{
    public readonly ?\DateInterval $interval;

    /**
     * @param \DateInterval|string|int|null $timeout
     */
    public function __construct(
        \DateInterval|string|int|null $timeout,
    ) {
        $this->interval = DateInterval::parseOrNull($timeout, DateInterval::FORMAT_SECONDS);
    }
}
