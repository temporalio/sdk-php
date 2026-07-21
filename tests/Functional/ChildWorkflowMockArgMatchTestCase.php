<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\WithChildWorkflow;
use Temporal\Workflow\WorkflowExecution;

final class ChildWorkflowMockArgMatchTestCase extends WorkflowTestCase
{
    public function testMatchingArgumentsSelectTheirOwnMock(): void
    {
        $this->workflowMocks->expectCompletionWhen('SimpleWorkflow', ['child A'], 'result-A');
        $this->workflowMocks->expectCompletionWhen('SimpleWorkflow', ['child B'], 'result-B');

        $runA = $this->workflowClient->start(
            $this->workflowClient->newWorkflowStub(WithChildWorkflow::class),
            'A',
        );
        self::assertSame('Child: result-A', $runA->getResult('string', 10));
        $this->assertNotContainsEvent(
            $runA->getExecution(),
            EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED,
        );

        $runB = $this->workflowClient->start(
            $this->workflowClient->newWorkflowStub(WithChildWorkflow::class),
            'B',
        );
        self::assertSame('Child: result-B', $runB->getResult('string', 10));
    }

    public function testUnmatchedArgumentsRunTheRealChild(): void
    {
        $this->workflowMocks->expectCompletionWhen('SimpleWorkflow', ['child A'], 'result-A');

        $run = $this->workflowClient->start(
            $this->workflowClient->newWorkflowStub(WithChildWorkflow::class),
            'Z',
        );

        self::assertSame('Child: CHILD Z', $run->getResult('string', 10));
        $this->assertContainsEvent(
            $run->getExecution(),
            EventType::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED,
        );
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
