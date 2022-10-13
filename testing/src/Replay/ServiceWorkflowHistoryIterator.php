<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use ArrayIterator;
use IteratorAggregate;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;

/**
 * @TODO: add implementation
 */
final class ServiceWorkflowHistoryIterator implements IteratorAggregate
{

    private WorkflowServiceClient $workflowServiceClient;
    private PollWorkflowTaskQueueResponse $task;

    public function __construct(WorkflowServiceClient $workflowServiceClient, PollWorkflowTaskQueueResponse $task) {
        $this->workflowServiceClient = $workflowServiceClient;
        $this->task = $task;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator();
    }
}
