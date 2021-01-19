<?php

declare(strict_types=1);

namespace Temporal\Tests\Workflow;

use Temporal\Activity\ActivityCancellationType;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Tests\Activity\HeartBeatActivity;

class CanceledHeartbeatWorkflow
{
    #[WorkflowMethod(name: 'CanceledHeartbeatWorkflow')]
    public function handler(): iterable
    {
        $act = Workflow::newActivityStub(
            HeartBeatActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(50)
                ->withCancellationType(ActivityCancellationType::WAIT_CANCELLATION_COMPLETED)
        );

        return yield $act->slow('test');
    }
}
