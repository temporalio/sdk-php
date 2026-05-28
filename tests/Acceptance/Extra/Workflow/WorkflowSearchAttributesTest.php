<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\WorkflowSearchAttributes;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\ChildWorkflowOptions;

/**
 * Tests marshalling for {@see ChildWorkflowOptions::$searchAttributes} and {@see WorkflowOptions::$searchAttributes}
 * Also covers {@see \Temporal\Workflow\WorkflowInfo::$searchAttributes}
 */
class WorkflowSearchAttributesTest extends TestCase
{
    #[Test]
    public function sendEmptySearchAttributes(
        #[Stub(
            'Extra_Workflow_WorkflowSearchAttributes',
            args: [
                [],
            ],
        )]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(timeout: 3);
        $this->assertSame([], $result, 'Workflow result contains resolved value');
    }

    #[Test]
    public function sendNullAsSearchAttributes(
        #[Stub(
            'Extra_Workflow_WorkflowSearchAttributes',
            args: [
                null,
            ],
        )]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult(timeout: 3);
        $this->assertNull($result);
    }

    #[Test]
    public function sendSimpleSearchAttributeSet(
        #[Stub(
            'Extra_Workflow_WorkflowSearchAttributes',
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
    #[WorkflowMethod(name: "Extra_Workflow_WorkflowSearchAttributes")]
    public function handle(?array $searchAttributes): \Generator
    {
        return yield Workflow::newChildWorkflowStub(
            TestWorkflowChild::class,
            ChildWorkflowOptions::new()
                ->withSearchAttributes($searchAttributes)
        )->handle();
    }
}

#[WorkflowInterface]
class TestWorkflowChild
{
    #[WorkflowMethod(name: "Extra_Workflow_WorkflowSearchAttributes_Child")]
    public function handle(): ?array
    {
        return Workflow::getInfo()->searchAttributes;
    }
}
