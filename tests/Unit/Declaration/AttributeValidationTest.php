<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use PHPUnit\Framework\TestCase;
use Temporal\Activity\Attribute\HeartbeatTimeout;
use Temporal\Activity\Attribute\ScheduleToCloseTimeout;
use Temporal\Activity\Attribute\ScheduleToStartTimeout;
use Temporal\Activity\Attribute\StartToCloseTimeout;
use Temporal\Activity\Attribute\Summary as ActivitySummary;
use Temporal\Activity\Attribute\TaskQueue as ActivityTaskQueue;
use Temporal\Workflow\Attribute\Summary as WorkflowSummary;
use Temporal\Workflow\Attribute\TaskQueue as WorkflowTaskQueue;
use Temporal\Workflow\Attribute\WorkflowExecutionTimeout;
use Temporal\Workflow\Attribute\WorkflowRunTimeout;
use Temporal\Workflow\Attribute\WorkflowStartDelay;
use Temporal\Workflow\Attribute\WorkflowTaskTimeout;

/**
 * @group unit
 * @group declaration
 */
class AttributeValidationTest extends TestCase
{
    // --- Activity timeout attributes: valid ---

    public function testHeartbeatTimeoutAcceptsPositiveValue(): void
    {
        $attr = new HeartbeatTimeout(30);
        $this->assertEquals(30, $attr->interval->totalSeconds);
    }

    public function testHeartbeatTimeoutAcceptsZero(): void
    {
        $attr = new HeartbeatTimeout(0);
        $this->assertEquals(0, $attr->interval->totalSeconds);
    }

    public function testHeartbeatTimeoutAcceptsStringInterval(): void
    {
        $attr = new HeartbeatTimeout('10 seconds');
        $this->assertEquals(10, $attr->interval->totalSeconds);
    }

    public function testScheduleToCloseTimeoutAcceptsPositiveValue(): void
    {
        $attr = new ScheduleToCloseTimeout(60);
        $this->assertEquals(60, $attr->interval->totalSeconds);
    }

    public function testScheduleToStartTimeoutAcceptsPositiveValue(): void
    {
        $attr = new ScheduleToStartTimeout(5);
        $this->assertEquals(5, $attr->interval->totalSeconds);
    }

    public function testStartToCloseTimeoutAcceptsPositiveValue(): void
    {
        $attr = new StartToCloseTimeout(120);
        $this->assertEquals(120, $attr->interval->totalSeconds);
    }

    // --- Activity timeout attributes: negative ---

    public function testHeartbeatTimeoutRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Heartbeat timeout must be non-negative.');
        new HeartbeatTimeout(-1);
    }

    public function testScheduleToCloseTimeoutRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ScheduleToClose timeout must be non-negative.');
        new ScheduleToCloseTimeout(-5);
    }

    public function testScheduleToStartTimeoutRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ScheduleToStart timeout must be non-negative.');
        new ScheduleToStartTimeout(-1);
    }

    public function testStartToCloseTimeoutRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('StartToClose timeout must be non-negative.');
        new StartToCloseTimeout(-10);
    }

    // --- Workflow timeout attributes: valid ---

    public function testWorkflowExecutionTimeoutAcceptsPositiveValue(): void
    {
        $attr = new WorkflowExecutionTimeout(3600);
        $this->assertEquals(3600, $attr->interval->totalSeconds);
    }

    public function testWorkflowRunTimeoutAcceptsPositiveValue(): void
    {
        $attr = new WorkflowRunTimeout(300);
        $this->assertEquals(300, $attr->interval->totalSeconds);
    }

    public function testWorkflowStartDelayAcceptsPositiveValue(): void
    {
        $attr = new WorkflowStartDelay(10);
        $this->assertEquals(10, $attr->interval->totalSeconds);
    }

    public function testWorkflowTaskTimeoutAcceptsPositiveValue(): void
    {
        $attr = new WorkflowTaskTimeout(30);
        $this->assertEquals(30, $attr->interval->totalSeconds);
    }

    // --- Workflow timeout attributes: string interval ---

    public function testWorkflowExecutionTimeoutAcceptsStringInterval(): void
    {
        $attr = new WorkflowExecutionTimeout('2 hours');
        $this->assertEquals(7200, $attr->interval->totalSeconds);
    }

    public function testWorkflowRunTimeoutAcceptsStringInterval(): void
    {
        $attr = new WorkflowRunTimeout('5 minutes');
        $this->assertEquals(300, $attr->interval->totalSeconds);
    }

    public function testWorkflowStartDelayAcceptsStringInterval(): void
    {
        $attr = new WorkflowStartDelay('30 seconds');
        $this->assertEquals(30, $attr->interval->totalSeconds);
    }

    public function testWorkflowTaskTimeoutAcceptsStringInterval(): void
    {
        $attr = new WorkflowTaskTimeout('45 seconds');
        $this->assertEquals(45, $attr->interval->totalSeconds);
    }

    // --- Workflow timeout attributes: negative ---

    public function testWorkflowExecutionTimeoutRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WorkflowExecutionTimeout must be non-negative.');
        new WorkflowExecutionTimeout(-1);
    }

    public function testWorkflowRunTimeoutRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WorkflowRunTimeout must be non-negative.');
        new WorkflowRunTimeout(-100);
    }

    public function testWorkflowStartDelayRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WorkflowStartDelay must be non-negative.');
        new WorkflowStartDelay(-5);
    }

    public function testWorkflowTaskTimeoutRejectsNegativeValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('WorkflowTaskTimeout must be non-negative.');
        new WorkflowTaskTimeout(-1);
    }

    // --- TaskQueue attributes ---

    public function testActivityTaskQueueAcceptsValidName(): void
    {
        $attr = new ActivityTaskQueue('my-queue');
        $this->assertEquals('my-queue', $attr->name);
    }

    public function testActivityTaskQueueRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TaskQueue name must not be empty.');
        new ActivityTaskQueue('');
    }

    public function testWorkflowTaskQueueAcceptsValidName(): void
    {
        $attr = new WorkflowTaskQueue('workflow-queue');
        $this->assertEquals('workflow-queue', $attr->name);
    }

    public function testWorkflowTaskQueueRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TaskQueue name must not be empty.');
        new WorkflowTaskQueue('');
    }

    // --- Summary attributes ---

    public function testActivitySummaryAcceptsValidText(): void
    {
        $attr = new ActivitySummary('Process payment');
        $this->assertEquals('Process payment', $attr->text);
    }

    public function testActivitySummaryRejectsEmptyText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Summary text must not be empty.');
        new ActivitySummary('');
    }

    public function testWorkflowSummaryAcceptsValidText(): void
    {
        $attr = new WorkflowSummary('Order processing workflow');
        $this->assertEquals('Order processing workflow', $attr->text);
    }

    public function testWorkflowSummaryRejectsEmptyText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Summary text must not be empty.');
        new WorkflowSummary('');
    }
}
