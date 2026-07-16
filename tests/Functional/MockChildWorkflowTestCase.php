<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\WithChildStubWorkflow;
use Temporal\Tests\Workflow\WithChildWorkflow;
use Temporal\Workflow\WorkflowExecution;

final class MockChildWorkflowTestCase extends WorkflowTestCase
{
    public function testMockedChildWorkflowViaExecuteChildWorkflow(): void
    {
        $this->workflowMocks->expectCompletion('SimpleWorkflow', 'mocked-child-result');

        $workflow = $this->workflowClient->newWorkflowStub(WithChildWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'input');

        self::assertSame('Child: mocked-child-result', $run->getResult('string', 10));
        $this->assertNotContainsEvent(
            $run->getExecution(),
            EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED,
        );
        $this->workflowMocks->assertInvoked('SimpleWorkflow');
    }

    public function testMockedChildWorkflowViaTypedStub(): void
    {
        $this->workflowMocks->expectCompletion('SimpleWorkflow', 'typed-stub-mock');

        $workflow = $this->workflowClient->newWorkflowStub(WithChildStubWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'input');

        self::assertSame('Child: typed-stub-mock', $run->getResult('string', 10));
        $this->assertNotContainsEvent(
            $run->getExecution(),
            EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED,
        );
    }

    public function testUnmockedChildWorkflowRunsForReal(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(WithChildWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'input');

        self::assertSame('Child: CHILD INPUT', $run->getResult('string', 10));
        $this->assertContainsEvent(
            $run->getExecution(),
            EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED,
        );
        $this->workflowMocks->assertNotInvoked('SimpleWorkflow');
    }

    public function testMockedChildWorkflowFailurePropagatesToParent(): void
    {
        $this->workflowMocks->expectFailure('SimpleWorkflow', new \RuntimeException('mocked child failure'));

        $workflow = $this->workflowClient->newWorkflowStub(WithChildWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'input');

        $this->expectException(WorkflowFailedException::class);
        $run->getResult('string', 10);
    }

    private function assertNotContainsEvent(WorkflowExecution $execution, int $event): void
    {
        $history = $this->workflowClient->getWorkflowHistory($execution, pageSize: 50);
        foreach ($history as $item) {
            if ($item->getEventType() === $event) {
                self::fail(\sprintf('Unexpected event %s found in history', EventType::name($event)));
            }
        }

        self::assertTrue(true);
    }

    private function assertContainsEvent(WorkflowExecution $execution, int $event): void
    {
        $history = $this->workflowClient->getWorkflowHistory($execution, pageSize: 50);
        foreach ($history as $item) {
            if ($item->getEventType() === $event) {
                self::assertTrue(true);
                return;
            }
        }

        self::fail(\sprintf('Expected event %s not found in history', EventType::name($event)));
    }
}
