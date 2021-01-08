<?php

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

class WithChildWorkflow
{
    #[WorkflowMethod(name: 'WithChildWorkflow')]
    public function handler(
        string $input
    ): iterable {
        $result = yield Workflow::executeChildWorkflow(
            'SimpleWorkflow',
            ['child ' . $input],
            Workflow\ChildWorkflowOptions::new()
        );

        return 'Child: ' . $result;
    }
}
