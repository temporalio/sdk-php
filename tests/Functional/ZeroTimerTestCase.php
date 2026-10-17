<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Testing\TemporalServer;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\ZeroTimerWorkflow;

final class ZeroTimerTestCase extends TestCase
{
    private WorkflowClient $workflowClient;

    public function testZeroIntervalTimerFires(): void
    {
        self::assertSame('done', $this->runWith(0));
    }

    public function testNonZeroIntervalTimerFires(): void
    {
        self::assertSame('done', $this->runWith(1));
    }

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(
            ServiceClient::create(TemporalServer::address()),
        );

        parent::setUp();
    }

    private function runWith(int $seconds): mixed
    {
        $workflow = $this->workflowClient->newWorkflowStub(
            ZeroTimerWorkflow::class,
            WorkflowOptions::new()->withTaskQueue('default'),
        );

        return $this->workflowClient->start($workflow, $seconds)->getResult('string', 10);
    }
}
