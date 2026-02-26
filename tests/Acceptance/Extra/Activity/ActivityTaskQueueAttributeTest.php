<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Activity\ActivityTaskQueueAttribute;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Activity\Attribute\ScheduleToCloseTimeout;
use Temporal\Activity\Attribute\TaskQueue;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ActivityTaskQueueAttributeTest extends TestCase
{
    #[Test]
    public static function activityRunsOnCustomTaskQueue(
        #[Stub('Extra_Activity_ActivityTaskQueueAttribute')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(type: 'array');

        self::assertSame('custom-activity-queue', $result['taskQueue']);
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Activity_ActivityTaskQueueAttribute")]
    public function handle()
    {
        return yield Workflow::newActivityStub(TestActivity::class)
            ->getTaskQueue();
    }
}

#[TaskQueue('custom-activity-queue')]
#[ScheduleToCloseTimeout(60)]
#[Activity\ActivityInterface(prefix: 'Extra_Activity_ActivityTaskQueueAttribute.')]
class TestActivity
{
    #[Activity\ActivityMethod]
    public function getTaskQueue(): array
    {
        return [
            'taskQueue' => Activity::getInfo()->taskQueue,
        ];
    }
}
