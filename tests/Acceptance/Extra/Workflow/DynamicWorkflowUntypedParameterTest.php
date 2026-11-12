<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflowUntypedParameter;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DynamicWorkflowUntypedParameterTest extends TestCase
{
    #[Test]
    public function untypedParameterReceivesEmptyCollection(
        #[Stub('Extra_Workflow_Dynamic_UntypedEmpty')]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_UntypedEmpty',
            'args' => [],
            'optional' => 'default',
        ], $stub->getResult('array', 5));
    }

    #[Test]
    public function untypedParameterReceivesOneCollectionAndKeepsOptionalDefault(
        #[Stub('Extra_Workflow_Dynamic_UntypedMixed', args: ['hello', 42, null, false, ['nested' => true]])]
        WorkflowStubInterface $stub,
    ): void {
        self::assertSame([
            'type' => 'Extra_Workflow_Dynamic_UntypedMixed',
            'args' => ['hello', 42, null, false, ['nested' => true]],
            'optional' => 'default',
        ], $stub->getResult('array', 5));
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(dynamic: true)]
    public function handle($values, string $optional = 'default'): array
    {
        return [
            'type' => Workflow::getInfo()->type->name,
            'args' => $values->getValues(),
            'optional' => $optional,
        ];
    }
}
