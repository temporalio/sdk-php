<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\Priority;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\Priority;
use Temporal\Exception\Failure\ApplicationFailure;
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
        $client->start($stub, true);
        $result = $stub->getResult('array');


        self::assertSame([2], $result['activity']);
        self::assertSame([1], $result['child']);
        self::assertSame([4], $result['workflow']);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Workflow_Priority")]
    public function handle(bool $runChild = false)
    {
        $activity = yield Workflow::executeActivity(
            'Extra_Workflow_Priority.handler',
            options: Activity\ActivityOptions::new()
                ->withScheduleToCloseTimeout('10 seconds')
                ->withPriority(Priority::new(2)),
        );

        Workflow\ChildWorkflowOptions::new()->priority->priorityKey === Workflow::getInfo()->priority->priorityKey or
        throw new ApplicationFailure('Child Workflow priority is not the same as the parent by default', 'error', true);

        if ($runChild) {
            $child = yield Workflow::executeChildWorkflow(
                'Extra_Workflow_Priority',
                [false],
                Workflow\ChildWorkflowOptions::new()->withPriority(Priority::new(1)),
                'array',
            );
        }

        return [
            'activity' => $activity,
            'workflow' => [Workflow::getInfo()->priority->priorityKey],
            'child' => $child['workflow'] ?? null,
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
