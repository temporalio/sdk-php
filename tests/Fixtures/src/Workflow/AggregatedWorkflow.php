<?php

namespace Temporal\Tests\Workflow;

use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface AggregatedWorkflow
{
    #[SignalMethod]
    public function addValue(
        string $value
    );

    #[WorkflowMethod]
    public function run(
        int $count
    );
}
