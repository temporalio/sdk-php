<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflowRequiredParameter;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\DynamicWorkflowFailureTestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DynamicWorkflowRequiredParameterTest extends DynamicWorkflowFailureTestCase
{
    #[Test]
    public function extraRequiredParameterFailsWithSdkException(
        #[Stub('Extra_Workflow_Dynamic_RequiredParameter', args: ['hello', 'world'])]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        $this->assertHandlerFailure($stub, $client, 'Extra_Workflow_Dynamic_RequiredParameter_Probe', 'Too few arguments to function ' . TestWorkflow::class . '::handle(), 1 passed and exactly 2 expected', \ArgumentCountError::class);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(dynamic: true)]
    public function handle(ValuesInterface $values, string $required): string
    {
        return $required;
    }
}

#[WorkflowInterface]
class ProbeWorkflow
{
    #[WorkflowMethod('Extra_Workflow_Dynamic_RequiredParameter_Probe')]
    public function handle()
    {
        return yield Workflow::sideEffect(static fn(): int => \getmypid());
    }
}
