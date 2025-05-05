<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\Priority;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\Priority;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class PriorityTest extends TestCase
{
    #[Test]
    public function instanceInPriority(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_Priority',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withPriority(Priority::new(4)),
        );

        /** @see TestWorkflow::handle() */
        $client->start($stub);
        $result = $stub->getResult('array');


        // todo uncomment after release RR >2025.1
        // self::assertSame([2], $result['activity']);
        self::assertSame([4], $result['workflow']);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Workflow_Priority")]
    public function handle()
    {
        $activity = yield Workflow::executeActivity(
            'Extra_Workflow_Priority.handler',
            options: Activity\ActivityOptions::new()
                ->withScheduleToCloseTimeout('10 seconds'),
                // todo uncomment after release RR >2025.1
                // ->withPriority(Priority::new(2)),
        );

        return [
            'activity' => $activity,
            'workflow' => [Workflow::getInfo()->priority->priorityKey],
        ];
    }
}

#[Activity\ActivityInterface(prefix: 'Extra_Workflow_Priority.')]
class TestActivity
{
    #[Activity\ActivityMethod]
    public function handler(): array
    {
        return [Activity::getInfo()->priority->priorityKey];
    }
}
