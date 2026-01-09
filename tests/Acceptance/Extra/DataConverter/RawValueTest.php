<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\DataConverter\RawValue;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Api\Common\V1\Payload;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\RawValue;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class RawValueTest extends TestCase
{
    #[Test]
    public function check(
        #[Stub('Extra_DataConverter_RawValue')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult();

        self::assertInstanceOf(RawValue::class, $result);
        self::assertInstanceOf(Payload::class, $result->getPayload());
        self::assertSame('hello world', $result->getPayload()->getData());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Extra_DataConverter_RawValue')]
    public function run()
    {
        $rawValue = new RawValue(new Payload(['data' => 'hello world']));

        $activity = Workflow::newActivityStub(
            RawValueActivity::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout('1 minute'),
        );

        return yield $activity->bypass($rawValue);
    }
}

#[ActivityInterface(prefix: 'RawValueActivity.')]
class RawValueActivity
{
    #[ActivityMethod]
    public function bypass(RawValue $arg)
    {
        return $arg;
    }
}
