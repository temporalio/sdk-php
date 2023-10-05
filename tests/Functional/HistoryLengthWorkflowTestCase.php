<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\ActivityMocker;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\HistoryLengthWorkflow;

final class HistoryLengthWorkflowTestCase extends TestCase
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

    public function testHistoryLengthIsUpdated(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(HistoryLengthWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'hello');

        $result = $run->getResult('array');

        $this->assertGreaterThan($result[1], $result[2]);
        $this->assertGreaterThan($result[2], $result[3]);
        $this->assertGreaterThan($result[3], $result[4]);
    }
}
