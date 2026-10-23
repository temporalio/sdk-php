<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\DynamicWorkflowArrayParameter;

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
class DynamicWorkflowArrayParameterTest extends DynamicWorkflowFailureTestCase
{
    #[Test]
    public function arrayParameterFailsWithSdkException(
        #[Stub('Extra_Workflow_Dynamic_ArrayParameter', args: [['hello', 42]])]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        ClientLogger $logger,
    ): void {
        $this->assertHandlerFailure($stub, $client, $logger, 'must be of type array');
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
