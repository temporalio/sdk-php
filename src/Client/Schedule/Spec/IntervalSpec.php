<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use DateInterval;
use Google\Protobuf\Duration;
use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 *  IntervalSpec matches times that can be expressed as:
 *  epoch + n * interval + phase
 *  where n is an integer.
 *  phase defaults to zero if missing. interval is required.
 *  Both interval and phase must be non-negative and are truncated to the nearest
 *  second before any calculations.
 *  For example, an interval of 1 hour with phase of zero would match every hour,
 *  on the hour. The same interval but a phase of 19 minutes would match every
 *  xx:19:00. An interval of 28 days with phase zero would match
 *  2022-02-17T00:00:00Z (among other times). The same interval with a phase of 3
 *  days, 5 hours, and 23 minutes would match 2022-02-20T05:23:00Z instead.
 *
 * @see \Temporal\Api\Schedule\V1\IntervalSpec
 */
final class IntervalSpec
{
    #[Marshal(name: 'interval', of: Duration::class)]
    public readonly DateInterval $interval;

    #[Marshal(name: 'phase', of: Duration::class)]
    public readonly DateInterval $phase;
}
