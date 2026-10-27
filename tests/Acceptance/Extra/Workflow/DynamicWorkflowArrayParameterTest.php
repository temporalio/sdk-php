<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflowArrayParameter;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\DynamicWorkflowFailureTestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DynamicWorkflowArrayParameterTest extends DynamicWorkflowFailureTestCase
{
    #[Test]
    public function arrayParameterFailsWithSdkException(
        #[Stub('Extra_Workflow_Dynamic_ArrayParameter', args: [['hello', 42]])]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
    ): void {
        $this->assertHandlerFailure($stub, $client, 'Extra_Workflow_Dynamic_ArrayParameter_Probe', TestWorkflow::class . '::handle(): Argument #1 ($values) must be of type array, Temporal\\DataConverter\\EncodedValues given');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(dynamic: true)]
    public function handle(array $values): array
    {
        return $values;
    }
}

#[WorkflowInterface]
class ProbeWorkflow
{
    #[WorkflowMethod('Extra_Workflow_Dynamic_ArrayParameter_Probe')]
    public function handle()
    {
        return yield Workflow::sideEffect(static fn(): int => \getmypid());
    }
}
