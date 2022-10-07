<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedRequest;

final class WorkflowTaskHandlerResult
{
    private string $workflowType;
    private ?RespondWorkflowTaskCompletedRequest $taskCompleted;
    private ?RespondWorkflowTaskFailedRequest $taskFailed;
    private ?RespondQueryTaskCompletedRequest $queryCompleted;
    private bool $completionCommand;

    public function __construct(
        string $workflowType,
        ?RespondWorkflowTaskCompletedRequest $taskCompleted,
        ?RespondWorkflowTaskFailedRequest $taskFailed,
        ?RespondQueryTaskCompletedRequest $queryCompleted,
        bool $completionCommand
    ) {
        $this->workflowType = $workflowType;
        $this->taskCompleted = $taskCompleted;
        $this->taskFailed = $taskFailed;
        $this->queryCompleted = $queryCompleted;
        $this->completionCommand = $completionCommand;
    }

    public function getTaskCompleted(): ?RespondWorkflowTaskCompletedRequest
    {
        return $this->taskCompleted;
    }

    public function getTaskFailed(): ?RespondWorkflowTaskFailedRequest
    {
        return $this->taskFailed;
    }

    public function getQueryCompleted(): ?RespondQueryTaskCompletedRequest
    {
        return $this->queryCompleted;
    }
}
