<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Info;

use DateTimeImmutable;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Workflow\WorkflowExecution;

/**
 * @see \Temporal\Api\Schedule\V1\ScheduleActionResult
 */
final class ScheduleActionResult
{
    /**
     * Time that the action should have been taken (according to the schedule, including jitter).
     */
    #[Marshal(name: 'schedule_time')]
    public readonly DateTimeImmutable $scheduleTime;

    /**
     * Time that the action was taken (real time).
     */
    #[Marshal(name: 'actual_time')]
    public readonly DateTimeImmutable $actualTime;

    /**
     * If action was {@see StartWorkflowAction}:
     */
    #[Marshal(name: 'start_workflow_result')]
    public readonly WorkflowExecution $startWorkflowResult;

    /**
     * The DTO is a result of a query, so it is not possible to create it manually.
     */
    private function __construct()
    {
    }
}
