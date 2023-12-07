<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use DateTimeImmutable;
use DateTimeInterface;
use Temporal\Api\Schedule\V1\BackfillRequest;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Internal\Support\DateTime;
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
     * @param DateTimeInterface $startTime Start of the range to evaluate schedule in.
     * @param DateTimeInterface $endTime End of the range to evaluate schedule in.
     */
    private function __construct(
        public readonly DateTimeInterface $startTime,
        public readonly DateTimeInterface $endTime,
        public readonly ScheduleOverlapPolicy $overlapPolicy = ScheduleOverlapPolicy::Unspecified,
    ) {
    }

    /**
     * @param DateTimeInterface|string $startTime Start of the range to evaluate schedule in.
     * @param DateTimeInterface|string $endTime End of the range to evaluate schedule in.
     * @param ScheduleOverlapPolicy $overlapPolicy Policy for overlaps.
     */
    public static function new(
        DateTimeInterface|string $startTime,
        DateTimeInterface|string $endTime,
        ScheduleOverlapPolicy $overlapPolicy = ScheduleOverlapPolicy::Unspecified,
    ): self {
        return new self(
            DateTime::parse($startTime, class: DateTimeImmutable::class),
            DateTime::parse($endTime, class: DateTimeImmutable::class),
            $overlapPolicy,
        );
    }

    /**
     * Start of the range to evaluate schedule in.
     */
    public function withStartTime(DateTimeInterface|string $dateTime): self
    {
        /** @see self::$startTime */
        return $this->with('startTime', DateTime::parse($dateTime, class: DateTimeImmutable::class));
    }

    /**
     * End of the range to evaluate schedule in.
     */
    public function withEndTime(DateTimeInterface|string $dateTime): self
    {
        /** @see self::$endTime */
        return $this->with('endTime', DateTime::parse($dateTime, class: DateTimeImmutable::class));
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
