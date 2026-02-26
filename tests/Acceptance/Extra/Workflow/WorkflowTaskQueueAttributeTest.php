<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\WorkflowTaskQueueAttribute;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\Attribute\TaskQueue;
use Temporal\Workflow\Attribute\WorkflowExecutionTimeout;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class WorkflowTaskQueueAttributeTest extends TestCase
{
    #[Test]
    public function workflowRunsOnCustomTaskQueue(
        WorkflowClientInterface $workflowClient,
    ): void {
        $workflow = $workflowClient->newWorkflowStub(
            TestWorkflow::class,
            WorkflowOptions::new()->withWorkflowExecutionTimeout('1 minute'),
        );

        $result = self::toArray($workflow->handle());

        $this->assertSame('custom-workflow-queue', $result['taskQueue']);
    }

    private static function toArray(mixed $value): array
    {
        return \json_decode(\json_encode($value), true);
    }
}

#[WorkflowInterface]
#[TaskQueue('custom-workflow-queue')]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Workflow_WorkflowTaskQueueAttribute")]
    #[WorkflowExecutionTimeout(60)]
    public function handle(): array
    {
        return [
            'taskQueue' => Workflow::getInfo()->taskQueue,
        ];
    }
}
