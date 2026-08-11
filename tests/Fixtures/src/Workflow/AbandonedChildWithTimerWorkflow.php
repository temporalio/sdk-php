<?php

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class AbandonedChildWithTimerWorkflow
{
    #[WorkflowMethod('abandoned_workflow')]
    public function wait(int $timeoutInSeconds)
    {
        Workflow::timer($timeoutInSeconds);
        return 'Hello from child';
    }
}
