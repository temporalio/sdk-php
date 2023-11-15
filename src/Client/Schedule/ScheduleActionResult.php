<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use DateTimeImmutable;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Workflow\WorkflowExecution;

final class ScheduleActionResult
{
    /**
     * Time that the action was taken (according to the schedule, including jitter).
     */
    #[Marshal(name: 'schedule_time')]
    protected DateTimeImmutable $scheduleTime;

    /**
     * Time that the action was taken (real time).
     */
    #[Marshal(name: 'actual_time')]
    protected DateTimeImmutable $actualTime;

    /**
     * If action was start_workflow:
     */
    #[Marshal(name: 'start_workflow_result')]
    protected WorkflowExecution $start_workflow_result;
}
