<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use DateTimeImmutable;

final class ScheduleInfo
{
    /**
     * Number of actions taken by this schedule.
     *
     * @var int<0, max>
     */
    public readonly int $numActions;

    /**
     * Number of times a scheduled Action was skipped due to missing the catchup window.
     */
    public readonly int $numActionsMissedCatchupWindow;

    /**
     * Number of Actions skipped due to overlap.
     */
    public readonly int $numActionsSkippedOverlap;

    /**
     * Currently-running workflows started by this schedule. (There might be
     * more than one if the overlap policy allows overlaps.)
     *
     * @var ScheduleWorkflowExecution[]
     */
    public readonly array $runningWorkflows;

    /**
     * Most recent 10 Actions started (including manual triggers).
     * Sorted from older start time to newer.
     *
     * @var ScheduleActionResult[]
     */
    public readonly array $recentActions;

    /**
     * Next 10 scheduled Action times.
     *
     * @var DateTimeImmutable[]
     */
    public readonly array $nextActionTimes;

    /**
     * When the schedule was created.
     */
    public readonly DateTimeImmutable $createdAt;

    /**
     * When a schedule was last updated.
     */
    public readonly DateTimeImmutable $lastUpdateAt;
}
