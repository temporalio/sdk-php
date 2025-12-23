<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Activity\RetryOnError;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\RetryOptions;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * # Retrying activities on error
 *
 * Failed activities can retry in a number of ways. This is configurable by retry policies that govern if and
 * how a failed activity may retry.
 *
 * ## Feature implementation
 *
 *  Workflow executes activity with 5 max attempts and low backoff
 *  Activity errors every time with the attempt that failed
 *  Workflow waits on activity and re-bubbles its same error
 *  Confirm the right attempt error message is present
 */
class RetryOnErrorTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Activity_CancelTryCancel')]
        WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->getResult();
        } catch (WorkflowFailedException $e) {
            self::assertInstanceOf(ActivityFailure::class, $e->getPrevious());
            /** @var ActivityFailure $failure */
            $failure = $e->getPrevious()->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $failure);
            self::assertStringContainsStringIgnoringCase('activity attempt 5 failed', $failure->getOriginalMessage());
            return;
        }

        throw new \Exception('Expected getResult() produced WorkflowFailedException');
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Harness_Activity_CancelTryCancel')]
    public function run(): iterable
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
                        ->withMaximumAttempts(5),
                ),
        )->alwaysFailActivity();
    }
}

#[ActivityInterface]
class FeatureActivity
{
    #[ActivityMethod('always_fail_activity')]
    public function alwaysFailActivity(): string
    {
        $attempt = Activity::getInfo()->attempt;
        throw new ApplicationFailure(
            message: "activity attempt {$attempt} failed",
            type: "CustomError",
            nonRetryable: false,
        );
    }
}
