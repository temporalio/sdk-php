<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use Google\Protobuf\Duration;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Traits\CloneWith;

/**
 *  IntervalSpec matches times that can be expressed as:
 *  epoch + n * interval + phase
 *  where n is an integer.
 *  {@see self::$pahse} defaults to zero if missing. {@see self::$interval} is required.
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
    use CloneWith;

    private function __construct(
        #[Marshal(name: 'interval', of: Duration::class)]
        public readonly \DateInterval $interval,

        #[Marshal(name: 'phase', of: Duration::class)]
        public readonly \DateInterval $phase,
    ) {
    }

    public static function new(mixed $interval, mixed $phase = null): self
    {
        assert(DateInterval::assert($interval));
        $interval = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);

        assert($phase === null or DateInterval::assert($phase));
        $phase = DateInterval::parse($phase ?? new \DateInterval('PT0S'), DateInterval::FORMAT_SECONDS);

        return new self($interval, $phase);
    }

    public function withInterval(mixed $interval): self
    {
        assert(DateInterval::assert($interval));
        $interval = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);

        return $this->with('interval', $interval);
    }

    public function withPhase(mixed $phase): self
    {
        assert(DateInterval::assert($phase));
        $phase = DateInterval::parse($phase, DateInterval::FORMAT_SECONDS);

        return $this->with('phase', $phase);
    }
}
