<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use DateTimeImmutable;
use Temporal\Api\Schedule\V1\BackfillRequest;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Internal\Traits\CloneWith;

/**
 * Describes a time periods and policy and takes Actions as if that time passed by right now, all at once.
 *
 * @see BackfillRequest
 */
final class BackfillPeriod
{
    use CloneWith;

    /**
     * @param DateTimeImmutable $startTime Start of the range to evaluate schedule in.
     * @param DateTimeImmutable $endTime End of the range to evaluate schedule in.
     */
    private function __construct(
        public readonly DateTimeImmutable $startTime,
        public readonly DateTimeImmutable $endTime,
        public readonly ScheduleOverlapPolicy $overlapPolicy = ScheduleOverlapPolicy::Unspecified,
    ) {
    }

    /**
     * @param DateTimeImmutable $startTime Start of the range to evaluate schedule in.
     * @param DateTimeImmutable $endTime End of the range to evaluate schedule in.
     * @param ScheduleOverlapPolicy $overlapPolicy Policy for overlaps.
     * @return static
     */
    public function new(
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime,
        ScheduleOverlapPolicy $overlapPolicy = ScheduleOverlapPolicy::Unspecified,
    ): self {
        return new self($startTime, $endTime, $overlapPolicy);
    }

    /**
     * Start of the range to evaluate schedule in.
     */
    public function withStartTime(DateTimeImmutable $startTime): self
    {
        /** @see self::$startTime */
        return $this->with('startTime', $startTime);
    }

    /**
     * End of the range to evaluate schedule in.
     */
    public function withEndTime(DateTimeImmutable $endTime): self
    {
        /** @see self::$endTime */
        return $this->with('endTime', $endTime);
    }

    /**
     * Policy for overlaps.
     */
    public function withOverlapPolicy(ScheduleOverlapPolicy $overlapPolicy): self
    {
        /** @see self::$overlapPolicy */
        return $this->with('overlapPolicy', $overlapPolicy);
    }
}
