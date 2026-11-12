<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflow;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DynamicWorkflowTest extends TestCase
{
    #[Test]
    public function noParameters(
        #[Stub('Extra_Workflow_Dynamic_Empty')]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_Empty',
            'args' => [],
        ], $stub->getResult('array'));
    }

    #[Test]
    public function scalarParameters(
        #[Stub('Extra_Workflow_Dynamic_Scalars', args: ['hello', 42])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_Scalars',
            'args' => ['hello', 42],
        ], $stub->getResult('array'));
    }

    #[Test]
    public function structuredParameters(
        #[Stub('Extra_Workflow_Dynamic_Structured', args: [
            'pipeline', 0, ['steps' => ['publish', 'notify'], 'enabled' => false],
        ])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_Structured',
            'args' => ['pipeline', 0, ['steps' => ['publish', 'notify'], 'enabled' => false]],
        ], $stub->getResult('array'));
    }

    #[Test]
    public function mixedParameters(
        #[Stub('Extra_Workflow_Dynamic_Mixed', args: ['', 0, null, false, 3.5, ['first', 'second'], null])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_Mixed',
            'args' => ['', 0, null, false, 3.5, ['first', 'second'], null],
        ], $stub->getResult('array'));
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(dynamic: true)]
    public function handle(ValuesInterface $args): array
    {
        return [
            'type' => Workflow::getInfo()->type->name,
            'args' => $args->getValues(),
        ];
    }
}
