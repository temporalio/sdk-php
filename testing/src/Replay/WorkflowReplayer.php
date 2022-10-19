<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

/** Replays a workflow given its history. Useful for backwards compatibility testing. */
final class WorkflowReplayer
{
    /**
     * Replays workflow from a resource that contains a json serialized history.
     */
    public function replayWorkflowExecutionFromFile(string $filename, string $workflowClass): void
    {
        $workflowExecutionHistory = WorkflowExecutionHistory::fromFile($filename);
        $this->replayWorkflowExecution($workflowExecutionHistory, $workflowClass);
    }

    private function replayWorkflowExecution(WorkflowExecutionHistory $workflowExecutionHistory, string $workflowClass): void
    {
        // @TODO: not implemented yet
    }
}
