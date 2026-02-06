<?php

declare(strict_types=1);

namespace Temporal\Activity;

use Attribute;
use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Internal\Support\DateInterval;

/**
 * The queue timeout before the activity starts execution.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD), NamedArgumentConstructor]
final class ScheduleToStartTimeout
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
