<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use DateTimeImmutable;

/**
 * Describes a time periods and policy and takes Actions as if that time passed by right now, all at once.
 */
final class BackfillPeriod
{
    /**
     * @param DateTimeImmutable $startTime Start of the range to evaluate schedule in.
     * @param DateTimeImmutable $endTime End of the range to evaluate schedule in.
     */
    public function __construct(
        public readonly DateTimeImmutable $startTime,
        public readonly DateTimeImmutable $endTime,
        public readonly ScheduleOverlapPolicy $overlapPolicy = ScheduleOverlapPolicy::Unspecified,
    ) {
    }
}
