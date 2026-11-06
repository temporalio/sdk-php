<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflowNoParameter;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DynamicWorkflowNoParameterTest extends TestCase
{
    #[Test]
    public function handlerWithoutParametersIgnoresInput(
        #[Stub('Extra_Workflow_Dynamic_NoParameter', args: ['hello', 42, null])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame('Extra_Workflow_Dynamic_NoParameter', $stub->getResult('string', 5));
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(dynamic: true)]
    public function handle(): string
    {
        return Workflow::getInfo()->type->name;
    }
}
