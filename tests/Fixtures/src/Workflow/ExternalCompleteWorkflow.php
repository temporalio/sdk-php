<?php

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\Tests\Activity\SimpleActivity;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

class ExternalCompleteWorkflow
{
    #[WorkflowMethod(name: 'ExternalCompleteWorkflow')]
    public function handler(): iterable
    {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(10)
        );

        return yield $simple->external();
    }
}
