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
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;

class PriorityTest extends TestCase
{
    #[Test]
    public function priorityKey(
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

        self::assertSame(2, $result['activity']['priority_key']);
        self::assertSame(1, $result['child']['priority_key']);
        self::assertSame(4, $result['workflow']['priority_key']);
    }

    #[Test]
    public function fairness(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_Priority',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withPriority(
                    Priority::new()
                        ->withFairnessKey('parent-key')
                        ->withFairnessWeight(2.2),
                ),
        );

        /** @see TestWorkflow::handle() */
        $client->start($stub, true);
        $result = $stub->getResult('array');


        self::assertSame('activity-key', $result['activity']['fairness_key']);
        self::assertSame(5.4, $result['activity']['fairness_weight']);
        self::assertSame('parent-key', $result['workflow']['fairness_key']);
        self::assertSame(2.2, $result['workflow']['fairness_weight']);
        self::assertSame('child-key', $result['child']['fairness_key']);
        self::assertSame(3.3, $result['child']['fairness_weight']);
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
            options: ActivityOptions::new()
                ->withScheduleToCloseTimeout('10 seconds')
                ->withPriority(
                    Priority::new(2)
                        ->withFairnessKey('activity-key')
                        ->withFairnessWeight(5.4),
                ),
        );

        ChildWorkflowOptions::new()->priority->priorityKey === Workflow::getInfo()->priority->priorityKey or
        throw new ApplicationFailure('Child Workflow priority is not the same as the parent by default', 'error', true);

        if ($runChild) {
            $child = yield Workflow::executeChildWorkflow(
                'Extra_Workflow_Priority',
                [false],
                ChildWorkflowOptions::new()->withPriority(
                    Priority::new(1)
                        ->withFairnessKey('child-key')
                        ->withFairnessWeight(3.3),
                ),
                'array',
            );
        }

        return [
            'activity' => $activity,
            'workflow' => [
                'priority_key' => Workflow::getInfo()->priority->priorityKey,
                'fairness_key' => Workflow::getInfo()->priority->fairnessKey,
                'fairness_weight' => Workflow::getInfo()->priority->fairnessWeight,
            ],
            'child' => $child['workflow'] ?? null,
        ];
    }
}

#[ActivityInterface(prefix: 'Extra_Workflow_Priority.')]
class TestActivity
{
    #[ActivityMethod]
    public function handler(): array
    {
        return [
            'priority_key' => Activity::getInfo()->priority->priorityKey,
            'fairness_key' => Activity::getInfo()->priority->fairnessKey,
            'fairness_weight' => Activity::getInfo()->priority->fairnessWeight,
        ];
    }
}
