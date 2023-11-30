<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Info;

use DateTimeImmutable;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalArray;
use Temporal\Workflow\WorkflowExecution;

/**
 * ScheduleInfo describes other information about a schedule.
 *
 * @see \Temporal\Api\Schedule\V1\ScheduleInfo
 */
final class ScheduleInfo
{
    /**
     * Number of actions taken by this schedule.
     *
     * @var int<0, max>
     */
    #[Marshal(name: 'action_count')]
    public readonly int $numActions;

    /**
     * Number of times a scheduled Action was skipped due to missing the catchup window.
     */
    #[Marshal(name: 'missed_catchup_window')]
    public readonly int $numActionsMissedCatchupWindow;

    /**
     * Number of Actions skipped due to overlap.
     */
    #[Marshal(name: 'overlap_skipped')]
    public readonly int $numActionsSkippedOverlap;

    /**
     * Currently-running workflows started by this schedule. (There might be
     * more than one if the overlap policy allows overlaps.)
     *
     * @var WorkflowExecution
     */
    #[MarshalArray(name: 'running_workflows', of: WorkflowExecution::class)]
    public readonly array $runningWorkflows;

    /**
     * Most recent 10 Actions started (including manual triggers).
     * Sorted from older start time to newer.
     *
     * @var ScheduleActionResult[]
     */
    #[MarshalArray(name: 'recent_actions', of: ScheduleActionResult::class)]
    public readonly array $recentActions;

    /**
     * Next 10 scheduled Action times.
     *
     * @var DateTimeImmutable[]
     */
    #[MarshalArray(name: 'future_action_times', of: DateTimeImmutable::class)]
    public readonly array $nextActionTimes;

    /**
     * When the schedule was created.
     */
    #[Marshal(name: 'create_time')]
    public readonly DateTimeImmutable $createdAt;

    /**
     * When a schedule was last updated.
     */
    #[Marshal(name: 'update_time')]
    public readonly ?DateTimeImmutable $lastUpdateAt;

    /**
     * The DTO is a result of a query, so it is not possible to create it manually.
     */
    private function __construct()
    {
    }
}
