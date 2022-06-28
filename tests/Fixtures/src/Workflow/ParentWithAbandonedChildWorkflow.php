<?php

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ParentClosePolicy;
use Temporal\Workflow\WorkflowMethod;


#[Workflow\WorkflowInterface]
class ParentWithAbandonedChildWorkflow
{
    #[WorkflowMethod]
    public function start(int $childTimeoutInSeconds)
    {
        $child = Workflow::newChildWorkflowStub(
            AbandonedChildWithTimerWorkflow::class,
            ChildWorkflowOptions::new()
                ->withParentClosePolicy(ParentClosePolicy::POLICY_ABANDON)
        );

        $child->wait($childTimeoutInSeconds);

        return 'Welcome from parent';
    }
}
