<?php

declare(strict_types=1);


use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\ProtoPayloadWorkflow;

final class DataConverterTestCase extends TestCase
{
    private WorkflowClient $workflowClient;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(
            ServiceClient::create('127.0.0.1:7233')
        );

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testProtobufWorkflow(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(ProtoPayloadWorkflow::class);

        $run = $this->workflowClient->start($workflow);
        $run->getResult(\Temporal\Api\Common\V1\WorkflowExecution::class, 5);

        $this->assertTrue(true);
    }
}
