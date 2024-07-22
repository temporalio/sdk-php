<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\CancelTryCancel;

use Temporal\Activity;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class FeatureWorkflow
{
    private string $result = '';

    #[WorkflowMethod('Workflow')]
    public function run()
    {
        # Start workflow
        $activity = Workflow::newActivityStub(
            FeatureActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout('1 minute')
                ->withHeartbeatTimeout('5 seconds')
                # Disable retry
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(1))
                ->withCancellationType(Activity\ActivityCancellationType::TryCancel)
        );

        $scope = Workflow::async(static fn() => $activity->cancellableActivity());

        # Sleep for short time (force task turnover)
        yield Workflow::timer(1);

        try {
            $scope->cancel();
            yield $scope;
        } catch (CanceledFailure) {
            # Expected
        }

        # Wait for activity result
        yield Workflow::awaitWithTimeout('5 seconds', fn() => $this->result !== '');

        return $this->result;
    }

    #[Workflow\SignalMethod('activity_result')]
    public function activityResult(string $result)
    {
        $this->result = $result;
    }
}
