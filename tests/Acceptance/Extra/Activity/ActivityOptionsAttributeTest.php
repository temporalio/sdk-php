<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityOptionsAttribute;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\Attribute\HeartbeatTimeout;
use Temporal\Activity\Attribute\ScheduleToCloseTimeout;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Common\Priority;
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

        // RetryPolicy: all properties
        self::assertSame(20, $result['retryOptions']['maximumAttempts']);
        self::assertEqualsWithDelta(3.0, $result['retryOptions']['backoffCoefficient'], 0.001);
        self::assertSame(1, $result['retryOptions']['initialInterval']);
        self::assertSame(120, $result['retryOptions']['maximumInterval']);
        self::assertSame(['SomeActivityException'], $result['retryOptions']['nonRetryableExceptions']);
        // HeartbeatTimeout('10 seconds')
        self::assertSame(10, $result['heartbeatTimeout']);
        // Priority: server resolves by inheritance from parent workflow
        self::assertGreaterThanOrEqual(0, $result['priority']['priorityKey']);
        self::assertIsString($result['priority']['fairnessKey']);
        self::assertIsNumeric($result['priority']['fairnessWeight']);
    }

    #[Test]
    public static function activityOptionsOverriddenInCode(
        #[Stub('Extra_Activity_ActivityOptionsAttribute', args: ['code'])]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');

        // Overridden: maximumAttempts
        self::assertSame(5, $result['retryOptions']['maximumAttempts']);
        // From attribute: rest of RetryPolicy
        self::assertEqualsWithDelta(3.0, $result['retryOptions']['backoffCoefficient'], 0.001);
        self::assertSame(1, $result['retryOptions']['initialInterval']);
        self::assertSame(120, $result['retryOptions']['maximumInterval']);
        self::assertSame(['SomeActivityException'], $result['retryOptions']['nonRetryableExceptions']);
        // Overridden: priority (all properties)
        self::assertSame(5, $result['priority']['priorityKey']);
        self::assertSame('act-override', $result['priority']['fairnessKey']);
        self::assertEqualsWithDelta(3.0, $result['priority']['fairnessWeight'], 0.001);
        // From attribute: heartbeatTimeout stays
        self::assertSame(10, $result['heartbeatTimeout']);
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
                ->getInfo();
        }

        return yield Workflow::newActivityStub(
            TestActivity::class,
            ActivityOptions::new()
                ->withRetryOptions(RetryOptions::new()->withMaximumAttempts(5))
                ->withPriority(new Priority(priorityKey: 5, fairnessKey: 'act-override', fairnessWeight: 3.0)),
        )->getInfo();
    }
}

#[ScheduleToCloseTimeout(60)]
#[HeartbeatTimeout(10)]
#[RetryOptions(
    initialInterval: '1 second',
    backoffCoefficient: 3.0,
    maximumInterval: '2 minutes',
    maximumAttempts: 20,
    nonRetryableExceptions: ['SomeActivityException'],
)]
#[Priority(priorityKey: 3, fairnessKey: 'act-tenant-1', fairnessWeight: 1.5)]
#[ActivityInterface(prefix: 'Extra_Activity_ActivityOptionsAttribute.')]
class TestActivity
{
    #[Activity\ActivityMethod]
    public function getInfo(): array
    {
        $info = Activity::getInfo();
        $retry = $info->retryOptions;

        return [
            'retryOptions' => [
                'maximumAttempts' => $retry?->maximumAttempts,
                'backoffCoefficient' => $retry?->backoffCoefficient,
                'initialInterval' => $retry?->initialInterval !== null
                    ? (int) \Carbon\CarbonInterval::instance($retry->initialInterval)->totalSeconds
                    : null,
                'maximumInterval' => $retry?->maximumInterval !== null
                    ? (int) \Carbon\CarbonInterval::instance($retry->maximumInterval)->totalSeconds
                    : null,
                'nonRetryableExceptions' => $retry?->nonRetryableExceptions ?? [],
            ],
            'heartbeatTimeout' => (int) \Carbon\CarbonInterval::instance($info->heartbeatTimeout)->totalSeconds,
            'priority' => [
                'priorityKey' => $info->priority->priorityKey,
                'fairnessKey' => $info->priority->fairnessKey,
                'fairnessWeight' => $info->priority->fairnessWeight,
            ],
        ];
    }
}
