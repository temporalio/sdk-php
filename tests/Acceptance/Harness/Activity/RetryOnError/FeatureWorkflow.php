<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\RetryOnError;

use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Workflow')]
    public function run()
    {
        # Allow 4 retries with basically no backoff
        yield Workflow::newActivityStub(
            FeatureActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout('1 minute')
                ->withRetryOptions(
                    (new RetryOptions())
                        ->withInitialInterval('1 millisecond')
                        # Do not increase retry backoff each time
                        ->withBackoffCoefficient(1)
                        # 5 total maximum attempts
                        ->withMaximumAttempts(5)
                ),
        )->alwaysFailActivity();
    }
}
