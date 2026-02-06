<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityOptionsAttribute;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Activity;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\RetryOptions;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ActivityOptionsAttributeTest extends TestCase
{
    #[Test]
    public static function activityOptionsFromAttribute(
        #[Stub('Extra_Activity_ActivityOptionsAttribute', args: ['attribute'])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');
        self::assertSame([
            "initial_interval" => ['seconds' => 1, 'nanos' => 0],
            "backoff_coefficient" => 3.0,
            "maximum_interval" => ['seconds' => 120, 'nanos' => 0],
            "maximum_attempts" => 20,
            "non_retryable_error_types" => [],
        ], $result);
    }

    #[Test]
    public static function activityOptionsOverriddenInCode(
        #[Stub('Extra_Activity_ActivityOptionsAttribute', args: ['code'])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');
        // В коде мы переопределяем maximum_attempts на 5
        self::assertSame(5, $result['maximum_attempts']);
        self::assertSame(3.0, $result['backoff_coefficient']); // Остальное должно остаться из атрибута
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Activity_ActivityOptionsAttribute")]
    public function handle(string $arg)
    {
        if ($arg === 'attribute') {
            return yield Workflow::newActivityStub(TestActivity::class)
                ->retryOptions();
        }

        return yield Workflow::newActivityStub(
            TestActivity::class,
            ActivityOptions::new()->withRetryOptions(
                RetryOptions::new()->withMaximumAttempts(5)
            )
        )->retryOptions();
    }
}

#[Activity\ScheduleToCloseTimeout(10)]
#[Activity\RetryPolicy(new RetryOptions(
    initialInterval: '1 second',
    backoffCoefficient: 3.0,
    maximumInterval: '2 minutes',
    maximumAttempts: 20,
))]
#[Activity\ActivityInterface(prefix: 'Extra_Activity_ActivityOptionsAttribute.')]
class TestActivity
{
    #[Activity\ActivityMethod]
    public function retryOptions()
    {
        return Activity::getInfo()->retryOptions;
    }
}
