<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\Enums\V1\HistoryEventFilterType;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Testing\Replay\Exception\NonDeterministicWorkflowException;
use Temporal\Testing\Replay\Exception\ReplayerException;
use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\SignalWorkflow;
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
                \unlink($file);
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

    /**
     * @group skip-on-test-server
     */
    public function testWorkflowHistoryObject(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SignalWorkflow::class);

        $run = $this->workflowClient->start($workflow);

        $workflow->addName('Albert');
        $workflow->addName('Bob');
        $workflow->addName('Cecil');
        $workflow->addName('David');
        $workflow->addName('Eugene');
        $workflow->exit();

        trap($run->getResult('array'));

        $history = $this->workflowClient->getWorkflowHistory(
            execution: $run->getExecution(),
            skipArchival: true,
        );

        /** Check there are {@see HistoryEvent} objects in history */
        $i = 0;
        foreach ($history as $event) {
            $this->assertInstanceOf(HistoryEvent::class, $event);
            ++$i;
        }

        // History has minimal count of events
        $this->assertGreaterThan(10, $i);
    }

    /**
     * @group skip-on-test-server
     */
    public function testReplayWorkflowHistory(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SignalWorkflow::class);

        $run = $this->workflowClient->start($workflow);

        $workflow->addName('Albert');
        $workflow->addName('Bob');
        $workflow->addName('Cecil');
        $workflow->addName('David');
        $workflow->addName('Eugene');
        $workflow->exit();
        $run->getResult('array');

        $history = $this->workflowClient->getWorkflowHistory(
            execution: $run->getExecution(),
            skipArchival: true,
        )->getHistory();

        $this->assertGreaterThan(10, \count(\iterator_to_array($history->getEvents(), false)));

        // Run without Workflow Type specifying
        (new WorkflowReplayer())->replayHistory($history);
    }

    /**
     * Broke the history and replay it again
     * @group skip-on-test-server
     */
    public function testReplayBrokenWorkflowHistory(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SignalWorkflow::class);

        $run = $this->workflowClient->start($workflow);

        $workflow->addName('Albert');
        $workflow->addName('Bob');
        $workflow->addName('Cecil');
        $workflow->addName('David');
        $workflow->addName('Eugene');
        $workflow->exit();
        $run->getResult('array');

        $history = $this->workflowClient->getWorkflowHistory(
            execution: $run->getExecution(),
            skipArchival: true,
        )->getHistory();

        $this->assertGreaterThan(10, \count(\iterator_to_array($history->getEvents(), false)));

        (new WorkflowReplayer())->replayHistory($history, 'Signal.greet');

        // Broke the history and replay it again.
        /** @var HistoryEvent $event */
        foreach ($history->getEvents() as $event) {
            if ($event->getEventType() !== EventType::EVENT_TYPE_WORKFLOW_EXECUTION_SIGNALED) {
                continue;
            }
            // Replace `addName` signals with `undefinedSignal`.
            if ($event->getWorkflowExecutionSignaledEventAttributes()?->getSignalName() !== 'addName') {
                $event->getWorkflowExecutionSignaledEventAttributes()->setSignalName('undefinedSignal');
            }
        }

        $this->expectException(NonDeterministicWorkflowException::class);
        (new WorkflowReplayer())->replayHistory($history, 'Signal.greet');
    }

    /**
     * Filter event type
     * @group skip-on-test-server
     */
    public function testFilterHistory(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(SignalWorkflow::class);

        $run = $this->workflowClient->start($workflow);

        $workflow->addName('Albert');
        $workflow->addName('Bob');
        $workflow->addName('Cecil');
        $workflow->addName('David');
        $workflow->addName('Eugene');
        $workflow->exit();
        $run->getResult('array');

        $history = $this->workflowClient->getWorkflowHistory(
            execution: $run->getExecution(),
            historyEventFilterType: HistoryEventFilterType::HISTORY_EVENT_FILTER_TYPE_CLOSE_EVENT,
            skipArchival: true
        )->getHistory();

        $this->assertCount(1, \iterator_to_array($history->getEvents(), false));
    }

    /**
     * todo: uncomment when timeout will be implemented in Workflow Client calls
     *
     * @group skip-on-test-server
     */
    // public function testWaitNewEvent(): void
    // {
    //     $workflow = $this->workflowClient->newWorkflowStub(SignalWorkflow::class);
    //
    //     $run = $this->workflowClient->start($workflow);
    //
    //     $workflow->addName('Albert');
    //     $workflow->addName('Albert');
    //     $workflow->addName('Albert');
    //     $workflow->addName('Albert');
    //     $workflow->addName('Albert');
    //     $run->getResult('array');
    //
    //     $this->expectException(TimeoutException::class);
    //
    //     $this->workflowClient->withTimeout(1)->getWorkflowHistory(
    //         execution: $run->getExecution(),
    //         waitNewEvent: true,
    //         skipArchival: true,
    //     )->getHistory();
    // }

    /**
     * Send invalid history object to replay
     */
    public function testReplayInvalidHistory(): void
    {
        $this->expectException(NonDeterministicWorkflowException::class);

        (new WorkflowReplayer())->replayHistory(new History(), 'Signal.greet');
    }
}
