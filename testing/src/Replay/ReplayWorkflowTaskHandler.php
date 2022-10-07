<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest;

class ReplayWorkflowTaskHandler
{
    public function __construct()
    {
    }

    public function handleWorkflowTask(PollWorkflowTaskQueueResponse $workflowTask): WorkflowTaskHandlerResult
    {
        // @TODO: implement logic. Currently for testing mock success response with 'hello' json message.
        return new WorkflowTaskHandlerResult($workflowTask->getWorkflowType()->getName(), null, null, null, false);
    }
}
