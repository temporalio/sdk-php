<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\ActivityMocker;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\SimpleWorkflow;

final class SimpleWorkflowTestCase extends TestCase
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

    public function testWorkflowReturnsUpperCasedInput(): void
    {
        $this->activityMocks->expectCompletion('SimpleActivity.echo', 'world');
        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'hello');
        $this->assertSame('world', $run->getResult('string'));
    }
}
