<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\NonDetermenisticWorkflow;
use Temporal\Tests\Workflow\SimpleWorkflow;

final class ReplayerTestCase extends TestCase
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

    public function testReplayWorkflowFromServer(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);

        $run = $this->workflowClient->start($workflow, 'hello');
        $run->getResult('string');

        (new WorkflowReplayer())->replayFromServer(
            $run->getExecution(),
            'SimpleWorkflow',
        );

        $this->assertTrue(true);
    }

    public function testReplayWorkflowFromFile(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);

        $run = $this->workflowClient->start($workflow, 'hello');
        $run->getResult('string');
        $file = \dirname(__DIR__, 2) . '/runtime/tests/history.json';
        try {
            \is_dir(\dirname($file)) or \mkdir(\dirname($file), recursive: true);
            if (\is_file($file)) {
                \unlink($file);
            }

            (new WorkflowReplayer())->downloadHistory($run->getExecution(), 'SimpleWorkflow', $file);
            $this->assertFileExists($file);

            (new WorkflowReplayer())->replayFromJSONPB('SimpleWorkflow', $file);
        } finally {
            if (\is_file($file)) {
                \unlink($file);
            }
        }
    }

    public function testReplayNonDetermenisticWorkflow(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(NonDetermenisticWorkflow::class);

        $run = $this->workflowClient->start($workflow);
        $run->getResult('string');

        $this->expectException(\Spiral\Goridge\RPC\Exception\ServiceException::class);
        $this->expectExceptionMessage('nondeterministic workflow');

        (new WorkflowReplayer())->replayFromServer(
            $run->getExecution(),
            'NonDetermenisticWorkflow',
        );
    }
}
