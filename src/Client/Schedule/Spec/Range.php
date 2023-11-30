<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Traits\CloneWith;

/**
 * Range represents a set of integer values, used to match fields of a calendar
 * time in StructuredCalendarSpec. If end < start, then end is interpreted as
 * equal to start. This means you can use a Range with start set to a value, and
 * end and step unset (defaulting to 0) to represent a single value.
 *
 * @see \Temporal\Api\Schedule\V1\Range
 */
final class Range
{
    use CloneWith;

    /**
     * @param int $start Start of range (inclusive).
     * @param int $end End of range (inclusive).
     * @param int $step Step (optional, default 1).
     */
    private function __construct(
        #[Marshal]
        public readonly int $start,
        #[Marshal]
        public readonly int $end,
        #[Marshal]
        public readonly int $step
    ) {
    }

    /**
     * @param int $start Start of range (inclusive).
     * @param int $end End of range (inclusive).
     * @param int $step Step (optional, default 1).
     */
    public static function new(int $start, int $end, int $step = 1): self
    {
        return new self($start, $end, $step);
    }

    /**
     * @param int $start Start of range (inclusive).
     */
    public function withStart(int $start): self
    {
        return $this->with('start', $start);
    }

    /**
     * @param int $end End of range (inclusive).
     */
    public function withEnd(int $end): self
    {
        \assert($end >= $this->start, 'End must be greater than or equal to start.');
        return $this->with('end', $end);
    }

    /**
     * @param positive-int $step Step (optional, default 1).
     */
    public function withStep(int $step): self
    {
        \assert($step > 0, 'Step must be greater than 0.');
        return $this->with('step', $step);
    }
}
