<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Replay;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Testing\Replay\WorkflowExecutionHistory;
use Temporal\Tests\Unit\UnitTestCase;

final class WorkflowExecutionHistoryTestCase extends UnitTestCase
{
    public function testItCanBeCreatedFromJsonString(): void
    {
        $executionHistory = WorkflowExecutionHistory::fromJson(file_get_contents(__DIR__ . '/history.json'));
        $this->assertIsCorrectHistory($executionHistory);
    }

    public function testItCanBeCreatedFromFile(): void
    {
        $executionHistory = WorkflowExecutionHistory::fromFile(__DIR__ . '/history.json');
        $this->assertIsCorrectHistory($executionHistory);
    }

    private function assertIsCorrectHistory(WorkflowExecutionHistory $workflowExecutionHistory): void
    {
        $this->assertInstanceOf(WorkflowExecutionHistory::class, $workflowExecutionHistory);
        $history = $workflowExecutionHistory->getHistory();
        $this->assertSame(14, $history->getEvents()->count());

        $last = $workflowExecutionHistory->getLastEvent();
        $this->assertSame(EventType::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED, $last->getEventType());

        $first = $workflowExecutionHistory->getFirstEvent();
        $queue = $first->getWorkflowExecutionStartedEventAttributes()->getTaskQueue()->getName();
        $this->assertSame('default', $queue);
    }
}
