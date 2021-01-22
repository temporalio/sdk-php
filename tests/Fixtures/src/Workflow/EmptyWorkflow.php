<?php

namespace Temporal\Tests\Workflow;

use Temporal\Workflow\WorkflowMethod;

#[Workflow\WorkflowInterface]
class EmptyWorkflow
{
    #[WorkflowMethod(name: 'EmptyWorkflow')]
    public function handler()
    {
        return 42;
    }
}
