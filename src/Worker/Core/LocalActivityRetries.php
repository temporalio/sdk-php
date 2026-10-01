<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Activity_result\DoBackoff;
use Coresdk\Workflow_commands\ScheduleLocalActivity;
use Coresdk\Workflow_commands\StartTimer;
use Coresdk\Workflow_commands\WorkflowCommand;

final class LocalActivityRetries
{
    /** @var array<int, ScheduleLocalActivity> */
    private array $scheduled = [];

    /** @var array<int, ScheduleLocalActivity> */
    private array $backoffs = [];

    public function schedule(ScheduleLocalActivity $activity): ScheduleLocalActivity
    {
        return $this->scheduled[$activity->getSeq()] = $activity;
    }

    public function isBackoffTimer(int $timerSeq): bool
    {
        return isset($this->backoffs[$timerSeq]);
    }

    public function backoff(int $seq, int $timerSeq, DoBackoff $backoff): WorkflowCommand
    {
        $activity = $this->scheduled[$seq];
        unset($this->scheduled[$seq]);
        $activity->setAttempt($backoff->getAttempt());
        $activity->setOriginalScheduleTime($backoff->getOriginalScheduleTime());
        $this->backoffs[$timerSeq] = $activity;

        return new WorkflowCommand(['start_timer' => new StartTimer([
            'seq' => $timerSeq,
            'start_to_fire_timeout' => $backoff->getBackoffDuration(),
        ])]);
    }

    public function retry(int $timerSeq, int $seq): WorkflowCommand
    {
        $activity = $this->backoffs[$timerSeq];
        unset($this->backoffs[$timerSeq]);
        $activity->setSeq($seq);

        return new WorkflowCommand(['schedule_local_activity' => $this->schedule($activity)]);
    }

    public function forget(int $seq): void
    {
        unset($this->scheduled[$seq], $this->backoffs[$seq]);
    }
}
