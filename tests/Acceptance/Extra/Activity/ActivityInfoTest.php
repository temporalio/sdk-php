<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityInfo;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Activity;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ActivityInfoTest extends TestCase
{
    #[Test]
    public static function retryPolicy(
        #[Stub('Extra_Activity_ActivityInfo', args: [TestWorkflow::ARG_RETRY_POLICY])]
        WorkflowStubInterface $stub,
    ): void {
        self::markTestSkipped('See https://github.com/temporalio/sdk-php/issues/602');

        $result = $stub->getResult(type: 'array');
        self::assertSame([
            "initial_interval" => ['seconds' => 1, 'nanos' => 0],
            "backoff_coefficient" => 3,
            "maximum_interval" => ['seconds' => 120, 'nanos' => 0],
            "maximum_attempts" => 20,
            "non_retryable_error_types" => [],
        ], $result);
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    public const ARG_RETRY_POLICY = 'retryPolicy';

    #[WorkflowMethod(name: "Extra_Activity_ActivityInfo")]
    public function handle(string $arg)
    {
        return yield match ($arg) {
            self::ARG_RETRY_POLICY => $this->getRetryPolicy(),
        };
    }

    private function getRetryPolicy(): PromiseInterface
    {
        return Workflow::newActivityStub(
            TestActivity::class,
            Activity\ActivityOptions::new()
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withMaximumAttempts(20)
                        ->withBackoffCoefficient(3.0)
                        ->withInitialInterval('1 second')
                        ->withMaximumInterval('2 minutes'),
                )
                ->withScheduleToCloseTimeout(10),
        )
            ->retryPolicy();
    }
}

#[Activity\ActivityInterface(prefix: 'Extra_Activity_ActivityInfo.')]
class TestActivity
{
    #[Activity\ActivityMethod]
    public function retryPolicy()
    {
        return Activity::getInfo()->retryPolicy;
    }
}
