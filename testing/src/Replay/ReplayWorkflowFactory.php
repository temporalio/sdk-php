<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

/**
 * @TODO: add implementation
 */
final class ReplayWorkflowFactory
{
    public function getWorkflow(string $workflowType): ReplayWorkflow
    {
        return new ReplayWorkflow();
    }
}
