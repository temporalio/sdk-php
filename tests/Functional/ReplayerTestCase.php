<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\Replay\Exception\NonDeterministicWorkflowException;
use Temporal\Testing\Replay\Exception\ReplayerException;
use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\WorkflowWithSequence;

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
        $workflow = $this->workflowClient->newWorkflowStub(WorkflowWithSequence::class);

        $run = $this->workflowClient->start($workflow, 'hello');
        $run->getResult('string');

        (new WorkflowReplayer())->replayFromServer(
            'WorkflowWithSequence',
            $run->getExecution(),
        );

        $this->assertTrue(true);
    }

    public function testReplayWorkflowFromFile(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(WorkflowWithSequence::class);

        $run = $this->workflowClient->start($workflow, 'hello');
        $run->getResult('string');
        $file = \dirname(__DIR__, 2) . '/runtime/tests/history.json';
        try {
            \is_dir(\dirname($file)) or \mkdir(\dirname($file), recursive: true);
            if (\is_file($file)) {
                \unlink($file);
            }

            (new WorkflowReplayer())->downloadHistory('WorkflowWithSequence', $run->getExecution(), $file);
            $this->assertFileExists($file);

            (new WorkflowReplayer())->replayFromJSON('WorkflowWithSequence', $file);
        } finally {
            if (\is_file($file)) {
                // \unlink($file);
            }
        }
    }

    public function testReplayNonDetermenisticWorkflow(): void
    {
        $file = \dirname(__DIR__, 1) . '/Fixtures/history/squence-workflow-damaged.json';

        $this->expectException(NonDeterministicWorkflowException::class);

        (new WorkflowReplayer())->replayFromJSON('WorkflowWithSequence', $file);
    }

    public function testReplayNonDetermenisticWorkflowThroughFirstDetermenisticEvents(): void
    {
        $file = \dirname(__DIR__, 1) . '/Fixtures/history/squence-workflow-damaged.json';

        (new WorkflowReplayer())->replayFromJSON('WorkflowWithSequence', $file, lastEventId: 11);

        $this->assertTrue(true);
    }

    public function testReplayUnexistingFile(): void
    {
        $file = \dirname(__DIR__, 1) . '/Fixtures/history/there-is-no-file.json';

        $this->expectException(ReplayerException::class);

        (new WorkflowReplayer())->replayFromJSON('WorkflowWithSequence', $file);
    }
}
