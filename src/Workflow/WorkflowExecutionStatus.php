<?php

declare(strict_types=1);

namespace Temporal\Workflow;

/**
 * @see \Temporal\Api\Enums\V1\WorkflowExecutionStatus
 */
enum WorkflowExecutionStatus: int
{
    case Unspecified = 0;
    case Running = 1;
    case Completed = 2;
    case Failed = 3;
    case Canceled = 4;
    case Terminated = 5;
    case ContinuedAsNew = 6;
    case TimedOut = 7;
}
