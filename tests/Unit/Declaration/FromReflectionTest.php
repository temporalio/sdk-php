<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use PHPUnit\Framework\TestCase;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\Priority;
use Temporal\Common\RetryOptions;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\Workflow\ChildWorkflowOptions;

/**
 * Tests that fromReflection() correctly reads all supported attributes.
 *
 * @group unit
 * @group declaration
 */
class FromReflectionTest extends TestCase
{
    // --- ActivityOptions::fromReflection() ---

    public function testActivityFromReflectionReadsAllAttributes(): void
    {
        $reflection = new \ReflectionClass(Fixtures\ActivityWithAllAttributes::class);
        $options = ActivityOptions::fromReflection($reflection);

        $this->assertSame('custom-queue', $options->taskQueue);
        $this->assertEquals(30, $options->scheduleToCloseTimeout->totalSeconds);
        $this->assertEquals(10, $options->scheduleToStartTimeout->totalSeconds);
        $this->assertEquals(20, $options->startToCloseTimeout->totalSeconds);
        $this->assertEquals(5, $options->heartbeatTimeout->totalSeconds);
        $this->assertSame(0, $options->cancellationType); // WaitCancellationCompleted
        $this->assertInstanceOf(RetryOptions::class, $options->retryOptions);
        $this->assertInstanceOf(Priority::class, $options->priority);
        $this->assertSame(5, $options->priority->priorityKey);
        $this->assertSame('Do important work', $options->summary);
    }

    public function testActivityFromReflectionReadsMethodAttributes(): void
    {
        $reflection = new \ReflectionMethod(Fixtures\ActivityWithMethodAttributes::class, 'doWork');
        $options = ActivityOptions::fromReflection($reflection);

        $this->assertSame('method-queue', $options->taskQueue);
        $this->assertEquals(60, $options->startToCloseTimeout->totalSeconds);
        $this->assertSame('Method summary', $options->summary);
    }

    public function testActivityFromReflectionReturnsDefaultsForNoAttributes(): void
    {
        $reflection = new \ReflectionClass(Fixtures\ActivityWithNoAttributes::class);
        $options = ActivityOptions::fromReflection($reflection);

        $this->assertNull($options->taskQueue);
        $this->assertEquals(0, $options->scheduleToCloseTimeout->totalSeconds);
        $this->assertEquals(0, $options->startToCloseTimeout->totalSeconds);
        $this->assertNull($options->retryOptions);
        $this->assertSame('', $options->summary);
    }

    // --- WorkflowOptions::fromReflection() ---

    public function testWorkflowFromReflectionReadsAllAttributes(): void
    {
        $reflection = new \ReflectionClass(Fixtures\WorkflowWithAllAttributes::class);
        $options = WorkflowOptions::fromReflection($reflection);

        $this->assertSame('workflow-queue', $options->taskQueue);
        $this->assertEquals(3600, $options->workflowExecutionTimeout->totalSeconds);
        $this->assertEquals(1800, $options->workflowRunTimeout->totalSeconds);
        $this->assertEquals(30, $options->workflowTaskTimeout->totalSeconds);
        $this->assertEquals(10, $options->workflowStartDelay->totalSeconds);
        $this->assertSame(IdReusePolicy::POLICY_REJECT_DUPLICATE, $options->workflowIdReusePolicy);
        $this->assertSame(WorkflowIdConflictPolicy::TerminateExisting, $options->workflowIdConflictPolicy);
        $this->assertInstanceOf(RetryOptions::class, $options->retryOptions);
        $this->assertSame(['key1' => 'value1'], $options->memo);
        $this->assertSame(['CustomField' => 'search-value'], $options->searchAttributes);
        $this->assertInstanceOf(Priority::class, $options->priority);
        $this->assertSame(3, $options->priority->priorityKey);
        $this->assertSame('Important workflow', $options->staticSummary);
    }

    public function testWorkflowFromReflectionReadsMethodAttributes(): void
    {
        $reflection = new \ReflectionMethod(Fixtures\WorkflowWithMethodAttributes::class, 'execute');
        $options = WorkflowOptions::fromReflection($reflection);

        $this->assertSame('method-wf-queue', $options->taskQueue);
        $this->assertEquals(7200, $options->workflowExecutionTimeout->totalSeconds);
        $this->assertSame('0 */2 * * *', $options->cronSchedule);
    }

    public function testWorkflowFromReflectionReturnsDefaultsForNoAttributes(): void
    {
        $reflection = new \ReflectionClass(Fixtures\WorkflowWithNoAttributes::class);
        $options = WorkflowOptions::fromReflection($reflection);

        // taskQueue has a default value from WorkerFactoryInterface::DEFAULT_TASK_QUEUE
        $this->assertEquals(0, $options->workflowExecutionTimeout->totalSeconds);
        $this->assertEquals(0, $options->workflowRunTimeout->totalSeconds);
        $this->assertNull($options->retryOptions);
        $this->assertSame('', $options->staticSummary);
    }

    // --- ChildWorkflowOptions::fromReflection() ---

    public function testChildWorkflowFromReflectionReadsAttributes(): void
    {
        $reflection = new \ReflectionClass(Fixtures\WorkflowWithAllAttributes::class);
        $options = ChildWorkflowOptions::fromReflection($reflection);

        $this->assertSame('workflow-queue', $options->taskQueue);
        $this->assertEquals(3600, $options->workflowExecutionTimeout->totalSeconds);
        $this->assertEquals(1800, $options->workflowRunTimeout->totalSeconds);
        $this->assertEquals(30, $options->workflowTaskTimeout->totalSeconds);
        $this->assertSame(IdReusePolicy::POLICY_REJECT_DUPLICATE, $options->workflowIdReusePolicy);
        $this->assertInstanceOf(RetryOptions::class, $options->retryOptions);
        $this->assertInstanceOf(Priority::class, $options->priority);
        $this->assertSame(3, $options->priority->priorityKey);
        $this->assertSame('Important workflow', $options->staticSummary);
    }
}
