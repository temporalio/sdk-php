<?php

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\SimpleActivity;

class SimpleWorkflow
{
    #[WorkflowMethod(name: 'SimpleWorkflow')]
    public function handler(
        string $input
    ): iterable {
        $simple = Workflow::newActivityStub(
            SimpleActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(5)
        );

        return yield $simple->echo($input);
    }
}
