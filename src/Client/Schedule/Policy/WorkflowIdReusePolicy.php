<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Policy;

/**
 * Defines how new runs of a workflow with a particular ID may or may not be allowed. Note that
 *  it is *never* valid to have two actively running instances of the same workflow id.
 *
 * @see \Temporal\Api\Enums\V1\WorkflowIdReusePolicy
 */
enum WorkflowIdReusePolicy: int
{
    case Unspecified = 0;

    /**
     * Allow starting a workflow execution using the same workflow id.
     */
    case AllowDuplicate = 1;

    /**
     * Allow starting a workflow execution using the same workflow id, only when the last
     * execution's final state is one of [terminated, cancelled, timed out, failed].
     */
    case AllowDuplicateFailedOnly = 2;

    /**
     * Do not permit re-use of the workflow id for this workflow. Future start workflow requests
     * could potentially change the policy, allowing re-use of the workflow id.
     */
    case RejectDuplicate = 3;

    /**
     * If a workflow is running using the same workflow ID, terminate it and start a new one.
     * If no running workflow, then the behavior is the same as ALLOW_DUPLICATE
     */
    case TerminateIfRunning = 4;
}
