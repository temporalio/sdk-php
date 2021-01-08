<?php

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\Bytes;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;

class BinaryWorkflow
{
    #[WorkflowMethod(name: 'BinaryWorkflow')]
    public function handler(
        Bytes $input
    ): iterable {
        $opts = ActivityOptions::new()->withStartToCloseTimeout(5);

        return yield Workflow::executeActivity('SimpleActivity.md5', [$input], $opts);
    }
}