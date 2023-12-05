<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Policy;

use Google\Protobuf\Duration;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Traits\CloneWith;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 *
 * @see \Temporal\Api\Schedule\V1\SchedulePolicies
 */
final class SchedulePolicies
{
    use CloneWith;

    /**
     * Policy for overlaps.
     * Note that this can be changed after a schedule has taken some actions,
     * and some changes might produce unintuitive results. In general, the later
     * policy overrides the earlier policy.
     */
    #[Marshal(name: 'overlap_policy')]
    public readonly ScheduleOverlapPolicy $overlapPolicy;

    /**
     * Policy for catchups:
     * If the Temporal server misses an action due to one or more components
     * being down, and comes back up, the action will be run if the scheduled
     * time is within this window from the current time.
     * This value defaults to 60 seconds, and can't be less than 10 seconds.
     */
    #[Marshal(name: 'catchup_window', of: Duration::class)]
    public readonly \DateInterval $catchupWindow;

    /**
     * If true, and a Workflow run fails or times out, pause the Schedule.
     * This applies after retry policies: the full chain of retries must fail to
     * trigger a pause here.
     */
    #[Marshal(name: 'pause_on_failure')]
    public readonly bool $pauseOnFailure;

    private function __construct()
    {
        $this->overlapPolicy = ScheduleOverlapPolicy::Unspecified;
        $this->catchupWindow = new \DateInterval('PT60S');
        $this->pauseOnFailure = false;
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Policy for overlaps.
     * Note that this can be changed after a schedule has taken some actions,
     * and some changes might produce unintuitive results. In general, the later
     * policy overrides the earlier policy.
     */
    public function withOverlapPolicy(ScheduleOverlapPolicy $overlapPolicy): self
    {
        return $this->with('overlapPolicy', $overlapPolicy);
    }

    /**
     * Policy for catchups:
     * If the Temporal server misses an action due to one or more components
     * being down, and comes back up, the action will be run if the scheduled
     * time is within this window from the current time.
     * This value defaults to 60 seconds, and can't be less than 10 seconds.
     *
     * @param DateIntervalValue $interval
     */
    public function withCatchupWindow(mixed $interval): self
    {
        \assert(DateInterval::assert($interval));
        $interval = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);
        // Can't be less than 10 seconds.
        \assert($interval->totalSeconds >= 10);

        return $this->with('catchupWindow', $interval);
    }

    /**
     * If true, and a workflow run fails or times out, turn on "paused".
     * This applies after retry policies: the full chain of retries must fail to
     * trigger a pause here.
     */
    public function withPauseOnFailure(bool $pauseOnFailure = true): self
    {
        return $this->with('pauseOnFailure', $pauseOnFailure);
    }
}
