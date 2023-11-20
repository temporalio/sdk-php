<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use DateInterval;
use Google\Protobuf\Duration;
use Temporal\Client\Schedule\ScheduleOverlapPolicy;
use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * @see \Temporal\Api\Schedule\V1\SchedulePolicies
 */
final class SchedulePolicies
{
    /**
     * Policy for overlaps.
     * Note that this can be changed after a schedule has taken some actions,
     * and some changes might produce unintuitive results. In general, the later
     * policy overrides the earlier policy.
     */
    #[Marshal(name: 'overlap_policy')]
    public ScheduleOverlapPolicy $overlapPolicy;

    /**
     * Policy for catchups:
     * If the Temporal server misses an action due to one or more components
     * being down, and comes back up, the action will be run if the scheduled
     * time is within this window from the current time.
     * This value defaults to 60 seconds, and can't be less than 10 seconds.
     */
    #[Marshal(name: 'catchup_window', of: Duration::class)]
    public DateInterval $catchupWindow;

    /**
     * If true, and a workflow run fails or times out, turn on "paused".
     * This applies after retry policies: the full chain of retries must fail to
     * trigger a pause here.
     */
    #[Marshal(name: 'pause_on_failure')]
    public bool $pauseOnFailure;
}
