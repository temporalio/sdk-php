<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\CancelTryCancel;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Client\ActivityCanceledException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * # Activity cancellation - Try Cancel mode
 *
 * Activities may be cancelled in three different ways, this feature spec covers the
 * Try Cancel mode.
 *
 * Each feature workflow in this folder should start an activity and cancel it
 * using the Try Cancel mode. The implementation should demonstrate that the activity
 * keeps receives a cancel request after the workflow has issued it, but the workflow
 * immediately should proceed with the activity result being cancelled.
 *
 * ## Detailed spec
 *
 *  When the SDK issues the activity cancel request command, server will write an
 * activity cancel requested event to history
 *  The workflow immediately resolves the activity with its result being cancelled
 *  Server will notify the activity cancellation has been requested via a response
 * to activity heartbeating
 *  The activity may ignore the cancellation request if it explicitly chooses to
 *
 * ## Feature implementation
 *
 *  Execute activity that heartbeats and checks cancellation
 *  If a minute passes without cancellation, send workflow a signal that it timed out
 *  If cancellation is received, send workflow a signal that it was cancelled
 *  Cancel activity and confirm cancellation error is returned
 *  Check in the workflow that the signal sent from the activity is showing it was cancelled
 */
class CancelTryCancelTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Activity_CancelTryCancel')]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame('cancelled', $stub->getResult(timeout: 10));
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private string $result = '';

    #[WorkflowMethod('Harness_Activity_CancelTryCancel')]
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
                ->withCancellationType(Activity\ActivityCancellationType::TryCancel),
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
    public function activityResult(string $result): void
    {
        $this->result = $result;
    }
}

#[ActivityInterface]
class FeatureActivity
{
    public function __construct(
        private readonly WorkflowClientInterface $client,
    ) {}

    #[ActivityMethod('cancellable_activity')]
    public function cancellableActivity(): void
    {
        # Heartbeat every second for a minute
        $result = 'timeout';
        try {
            for ($i = 0; $i < 5_0; $i++) {
                \usleep(100_000);
                Activity::heartbeat($i);
            }
        } catch (ActivityCanceledException $e) {
            $result = 'cancelled';
        } catch (\Throwable $e) {
            $result = 'unexpected';
        }

        # Send result as signal to workflow
        $execution = Activity::getInfo()->workflowExecution;
        $this->client
            ->newRunningWorkflowStub(FeatureWorkflow::class, $execution->getID(), $execution->getRunID())
            ->activityResult($result);
    }
}
