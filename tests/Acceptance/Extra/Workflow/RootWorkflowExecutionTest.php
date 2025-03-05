<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\RootWorkflowExecution;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class RootWorkflowExecutionTest extends TestCase
{
    #[Test]
    public static function check(#[Stub('Extra_Workflow_RootWorkflowExecution')]WorkflowStubInterface $stub): void
    {
        self::markTestSkipped('Waiting https://github.com/temporalio/sdk-go/issues/1848');
        self::assertSame('Test', $stub->getResult());
    }
}

#[WorkflowInterface]
class MainWorkflow
{
    #[WorkflowMethod('Extra_Workflow_RootWorkflowExecution')]
    public function run()
    {
        return yield Workflow::newChildWorkflowStub(ChildWorkflow::class)
            ->run('Test');
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    #[WorkflowMethod('Extra_Workflow_RootWorkflowExecution_Child')]
    public function run(string $input)
    {
        return yield Workflow::newChildWorkflowStub(ChildWorkflow2::class)
            ->run($input);
    }
}

#[WorkflowInterface]
class ChildWorkflow2
{
    #[WorkflowMethod('Extra_Workflow_RootWorkflowExecution_Child2')]
    public function run(string $input)
    {
        return $input;
    }
}
