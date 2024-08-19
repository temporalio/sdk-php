<?php

declare(strict_types=1);

namespace Temporal\Worker;

enum WorkflowPanicPolicy: int
{
    /**
     *  BlockWorkflow is the default policy for handling workflow panics and detected non-determinism.
     * This option causes workflow to get stuck in the workflow task retry loop.
     * It is expected that after the problem is discovered and fixed the workflows are going to continue
     * without any additional manual intervention.
     */
    case BlockWorkflow = 0;

    /**
     * FailWorkflow immediately fails workflow execution if workflow code throws panic or detects non-determinism.
     * This feature is convenient during development.
     * WARNING: enabling this in production can cause all open workflows to fail on a single bug or bad deployment.
     */
    case FailWorkflow = 1;
}
