<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\Basic;

use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Workflow')]
    public function run()
    {
        yield Workflow::newActivityStub(
            FeatureActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout('1 minute'),
        )->echo();

        return yield Workflow::newActivityStub(
            FeatureActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout('1 minute'),
        )->echo();
    }
}
