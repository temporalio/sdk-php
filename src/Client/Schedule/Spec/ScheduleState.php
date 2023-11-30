<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Spec;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Traits\CloneWith;

/**
 * ScheduleState describes the current state of a Schedule.
 *
 * @see \Temporal\Api\Schedule\V1\ScheduleState
 */
final class ScheduleState
{
    use CloneWith;

    /**
     * Informative human-readable message with contextual notes, e.g. the reason
     * a schedule is paused. The system may overwrite this message on certain
     * conditions, e.g. when pause-on-failure happens.
     */
    #[Marshal]
    public readonly string $notes;

    /**
     * If true, do not take any actions based on the schedule spec.
     */
    #[Marshal]
    public readonly bool $paused;

    /**
     * If {@see self::$limitedActions} is true, decrement {@see self::$remainingActions} after each
     * action, and do not take any more scheduled actions if {@see self::$remainingActions}
     * is zero. Actions may still be taken by explicit request (i.e. trigger
     * immediately or backfill). Skipped actions (due to overlap policy) do not
     * count against remaining actions.
     */
    #[Marshal(name: 'limited_actions')]
    public readonly bool $limitedActions;

    /**
     * The Actions remaining in this Schedule. Once this number hits 0, no further Actions are taken.
     */
    #[Marshal(name: 'remaining_actions')]
    public readonly int $remainingActions;

    private function __construct()
    {
        $this->notes = '';
        $this->paused = false;
        $this->limitedActions = false;
        $this->remainingActions = 0;
    }

    public static function new(): self
    {
        return new self();
    }

    /**
     * Informative human-readable message with contextual notes, e.g. the reason
     * a schedule is paused. The system may overwrite this message on certain
     * conditions, e.g. when pause-on-failure happens.
     */
    public function withNotes(string $notes): self
    {
        return $this->with('notes', $notes);
    }

    /**
     * If true, do not take any actions based on the schedule spec.
     */
    public function withPaused(bool $paused): self
    {
        return $this->with('paused', $paused);
    }

    /**
     * If {@see self::$limitedActions} is true, decrement {@see self::$remainingActions} after each
     * action, and do not take any more scheduled actions if {@see self::$remainingActions}
     * is zero. Actions may still be taken by explicit request (i.e. trigger
     * immediately or backfill). Skipped actions (due to overlap policy) do not
     * count against remaining actions.
     */
    public function withLimitedActions(bool $limitedActions): self
    {
        return $this->with('limitedActions', $limitedActions);
    }

    /**
     * The Actions remaining in this Schedule. Once this number hits 0, no further Actions are taken.
     */
    public function withRemainingActions(int $remainingActions): self
    {
        return $this->with('remainingActions', $remainingActions);
    }
}
