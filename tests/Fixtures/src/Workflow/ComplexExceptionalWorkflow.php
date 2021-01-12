<?php

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

class ComplexExceptionalWorkflow
{
    #[WorkflowMethod(name: 'ComplexExceptionalWorkflow')]
    public function handler()
    {
        $child = Workflow::newChildWorkflowStub(
            ExceptionalActivityWorkflow::class,
            Workflow\ChildWorkflowOptions::new()
        );

        return yield $child->handler();
    }
}
