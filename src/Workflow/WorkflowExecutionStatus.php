<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Api\Enums\V1\WorkflowExecutionStatus as Message;

/**
 * @see \Temporal\Api\Enums\V1\WorkflowExecutionStatus
 */
enum WorkflowExecutionStatus: int
{
    case Unspecified = Message::WORKFLOW_EXECUTION_STATUS_UNSPECIFIED;
    case Running = Message::WORKFLOW_EXECUTION_STATUS_RUNNING;
    case Completed = Message::WORKFLOW_EXECUTION_STATUS_COMPLETED;
    case Failed = Message::WORKFLOW_EXECUTION_STATUS_FAILED;
    case Canceled = Message::WORKFLOW_EXECUTION_STATUS_CANCELED;
    case Terminated = Message::WORKFLOW_EXECUTION_STATUS_TERMINATED;
    case ContinuedAsNew = Message::WORKFLOW_EXECUTION_STATUS_CONTINUED_AS_NEW;
    case TimedOut = Message::WORKFLOW_EXECUTION_STATUS_TIMED_OUT;
}
