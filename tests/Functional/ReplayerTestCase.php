<?php

declare(strict_types=1);

namespace Functional;

use RoadRunner\Temporal\DTO\V1\ReplayRequest;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Common\V1\WorkflowType;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\ActivityMocker;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\SimpleWorkflow;

final class ReplayerTestCase extends TestCase
{
    private WorkflowClient $workflowClient;
    private ActivityMocker $activityMocks;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(
            ServiceClient::create('localhost:7233')
        );
        $this->activityMocks = new ActivityMocker();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->activityMocks->clear();
        parent::tearDown();
    }

    public function testReplayWorkflow(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);

        $run = $this->workflowClient->start($workflow, 'hello');
        $run->getResult('string');

        $rpc = new RPC(Relay::create(Environment::fromGlobals()->getRPCAddress()), new ProtobufCodec());
        $rpc->call('temporal.ReplayWorkflow', new ReplayRequest([
            'workflow_execution' => new WorkflowExecution([
                'run_id' => $run->getExecution()->getRunID(),
                'workflow_id' => $run->getExecution()->getID(),
            ]),
            'workflow_type' => new WorkflowType([
                'name' => 'SimpleWorkflow',
            ]),
        ]));

        $this->assertTrue(true);
    }
}
