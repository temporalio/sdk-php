<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\ChildWorkflowSearchAttributes;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[CoversFunction('Temporal\Internal\Workflow\Process\Process::logRunningHandlers')]
class ChildWorkflowSearchAttributesTest extends TestCase
{
    #[Test]
    public function updateHandlersWithOneCall(
        #[Stub(
            'Extra_Workflow_ChildWorkflowSearchAttributes',
            args: [
                ['foo' => 'bar'],
            ],
        )]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('array', timeout: 3);
        $this->assertSame(['foo' => 'bar'], $result, 'Workflow result contains resolved value');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Workflow_ChildWorkflowSearchAttributes")]
    public function handle(array $searchAttributes): \Generator
    {
        return yield Workflow::newChildWorkflowStub(
            TestWorkflowChild::class,
            Workflow\ChildWorkflowOptions::new()
                ->withSearchAttributes($searchAttributes)
                ->withTaskQueue(Workflow::getInfo()->taskQueue)
        )->handle();
    }
}

#[WorkflowInterface]
class TestWorkflowChild
{
    #[WorkflowMethod(name: "Extra_Workflow_ChildWorkflowSearchAttributes_Child")]
    public function handle(): array
    {
        return Workflow::getInfo()->searchAttributes;
    }
}
