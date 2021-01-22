<?php

namespace Temporal\Tests\Fixtures\src\Workflow;

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

    #[WorkflowMethod(name: 'AggregatedWorkflow')]
    public function run(
        int $count
    );
}
