<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

/**
 * ScheduleState describes the current state of a schedule.
 *
 * @see \Temporal\Api\Schedule\V1\ScheduleState
 */
final class ScheduleState
{
    /**
     * Informative human-readable message with contextual notes, e.g. the reason
     * a schedule is paused. The system may overwrite this message on certain
     * conditions, e.g. when pause-on-failure happens.
     */
    public string $notes;

    /**
     * If true, do not take any actions based on the schedule spec.
     */
    public bool $paused;

    /**
     * If limited_actions is true, decrement remaining_actions after each
     * action, and do not take any more scheduled actions if remaining_actions
     * is zero. Actions may still be taken by explicit request (i.e. trigger
     * immediately or backfill). Skipped actions (due to overlap policy) do not
     * count against remaining actions.
     */
    public bool $limitedActions;

    /**
     * The Actions remaining in this Schedule. Once this number hits 0, no further Actions are taken.
     */
    public int $remainingActions;
}
