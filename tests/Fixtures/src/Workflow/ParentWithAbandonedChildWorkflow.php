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
    public function start(int $childTimeoutInSeconds, bool $shouldWaitForChild)
    {
        $child = Workflow::newUntypedChildWorkflowStub(
            'abandoned_workflow',
            ChildWorkflowOptions::new()
                ->withParentClosePolicy(ParentClosePolicy::POLICY_ABANDON)
        );

        yield $child->start($childTimeoutInSeconds);
        if ($shouldWaitForChild) {
           return yield $child->getResult();
        }

        return 'Welcome from parent';
    }
}
