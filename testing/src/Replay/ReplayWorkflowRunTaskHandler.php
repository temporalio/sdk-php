<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;

/**
 * @TODO: add implementation
 */
final class ReplayWorkflowRunTaskHandler
{
    public function __construct(ReplayWorkflow $replayWorkflow, PollWorkflowTaskQueueResponse $workflowTask) {
    }

    public function handleWorkflowTask(PollWorkflowTaskQueueResponse $workflowTask, ServiceWorkflowHistoryIterator $historyIterator): WorkflowTaskResult
    {
        return new WorkflowTaskResult([], [], false, false);
    }

    public function close(): void
    {
    }
}
