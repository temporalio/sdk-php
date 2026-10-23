<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflowScalarParameter;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\DynamicWorkflowFailureTestCase;
use Temporal\Tests\Acceptance\App\Logger\ClientLogger;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[Worker(pipelineProvider: [DynamicWorkflowFailureTestCase::class, 'pipelineProvider'])]
class DynamicWorkflowScalarParameterTest extends DynamicWorkflowFailureTestCase
{
    #[Test]
    public function scalarParameterFailsWithSdkException(
        #[Stub('Extra_Workflow_Dynamic_ScalarParameter', args: ['hello'])]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        ClientLogger $logger,
    ): void {
        $this->assertHandlerFailure($stub, $client, $logger, TestWorkflow::class . '::handle(): Argument #1 ($value) must be of type string, Temporal\\DataConverter\\EncodedValues given');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(dynamic: true)]
    public function handle(string $value): string
    {
        return $value;
    }
}
