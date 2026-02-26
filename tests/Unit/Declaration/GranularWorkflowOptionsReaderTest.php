<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use Spiral\Attributes\AttributeReader;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\Priority;
use Temporal\Common\RetryOptions;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Common\CronSchedule;
use Temporal\Workflow\Attribute\Memo;
use Temporal\Workflow\Attribute\SearchAttributes;
use Temporal\Workflow\Attribute\Summary;
use Temporal\Workflow\Attribute\TaskQueue;
use Temporal\Workflow\Attribute\WorkflowExecutionTimeout;
use Temporal\Workflow\Attribute\WorkflowIdConflictPolicy as WorkflowIdConflictPolicyAttr;
use Temporal\Workflow\Attribute\WorkflowIdReusePolicy as WorkflowIdReusePolicyAttr;
use Temporal\Workflow\Attribute\WorkflowRunTimeout;
use Temporal\Workflow\Attribute\WorkflowStartDelay;
use Temporal\Workflow\Attribute\WorkflowTaskTimeout;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * @group unit
 * @group declaration
 */
class GranularWorkflowOptionsReaderTest extends AbstractDeclaration
{
    public function testTaskQueueFromAttribute(): void
    {
        $reader = new WorkflowReader(new AttributeReader());
        $proto = $reader->fromClass(WorkflowWithTaskQueueOnly::class);

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(WorkflowOptions::class, $options);
        $this->assertSame('custom-workflow-queue', $options->taskQueue);
    }

    public function testTaskQueueFromMethodOverridesClass(): void
    {
        $reader = new WorkflowReader(new AttributeReader());
        $proto = $reader->fromClass(WorkflowWithTaskQueueOverride::class);

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(WorkflowOptions::class, $options);
        $this->assertSame('method-override-queue', $options->taskQueue);
    }

    public function testGranularOptionsFromClass(): void
    {
        $reader = new WorkflowReader(new AttributeReader());
        $proto = $reader->fromClass(WorkflowWithGranularOptionsOnClass::class);

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(WorkflowOptions::class, $options);
        // var_dump($options->workflowExecutionTimeout?->s);
        $this->assertSame('class-queue', $options->taskQueue);
        $this->assertSame(100, (int)$options->workflowExecutionTimeout->totalSeconds);
        $this->assertSame(50, (int)$options->workflowRunTimeout->totalSeconds);
        $this->assertSame(1, $options->retryOptions->maximumAttempts);
        $this->assertSame(IdReusePolicy::POLICY_REJECT_DUPLICATE, $options->workflowIdReusePolicy);
        $this->assertSame(WorkflowIdConflictPolicy::Fail, $options->workflowIdConflictPolicy);
    }

    public function testGranularOptionsFromMethod(): void
    {
        $reader = new WorkflowReader(new AttributeReader());
        $proto = $reader->fromClass(WorkflowWithGranularOptionsOnMethod::class);

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(WorkflowOptions::class, $options);
        $this->assertSame('method-queue', $options->taskQueue);
        $this->assertSame(10, (int)$options->workflowTaskTimeout->totalSeconds);
        $this->assertSame(5, (int)$options->workflowStartDelay->totalSeconds);
        $this->assertSame('0 * * * *', $options->cronSchedule);
        $this->assertSame(['key' => 'value'], $options->memo);
        $this->assertSame(['attr' => 'val'], $options->searchAttributes);
        $this->assertSame('my-summary', $options->staticSummary);
        $this->assertSame(7, $options->priority->priorityKey);
    }

    public function testGranularOptionsMerged(): void
    {
        $reader = new WorkflowReader(new AttributeReader());
        $proto = $reader->fromClass(WorkflowWithGranularMergedOptions::class);

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(WorkflowOptions::class, $options);
        $this->assertSame('method-queue', $options->taskQueue); // Overridden by method
        $this->assertSame(100, (int)$options->workflowExecutionTimeout->totalSeconds); // Inherited from class
        $this->assertSame(2, $options->retryOptions->maximumAttempts); // Overridden by method
    }
}

#[WorkflowInterface]
#[TaskQueue('class-queue')]
#[WorkflowExecutionTimeout(100)]
#[WorkflowRunTimeout(50)]
#[RetryOptions(maximumAttempts: 1)]
#[WorkflowIdReusePolicyAttr(IdReusePolicy::RejectDuplicate)]
#[WorkflowIdConflictPolicyAttr(WorkflowIdConflictPolicy::Fail)]
class WorkflowWithGranularOptionsOnClass
{
    #[WorkflowMethod]
    public function handle(): void {}
}

#[WorkflowInterface]
class WorkflowWithGranularOptionsOnMethod
{
    #[WorkflowMethod]
    #[TaskQueue('method-queue')]
    #[WorkflowTaskTimeout(10)]
    #[WorkflowStartDelay(5)]
    #[CronSchedule('0 * * * *')]
    #[Memo(['key' => 'value'])]
    #[SearchAttributes(['attr' => 'val'])]
    #[Summary('my-summary')]
    #[Priority(priorityKey: 7)]
    public function handle(): void {}
}

#[WorkflowInterface]
#[TaskQueue('custom-workflow-queue')]
class WorkflowWithTaskQueueOnly
{
    #[WorkflowMethod]
    public function handle(): void {}
}

#[WorkflowInterface]
#[TaskQueue('class-level-queue')]
class WorkflowWithTaskQueueOverride
{
    #[WorkflowMethod]
    #[TaskQueue('method-override-queue')]
    public function handle(): void {}
}

#[WorkflowInterface]
#[TaskQueue('class-queue')]
#[WorkflowExecutionTimeout(100)]
#[RetryOptions(maximumAttempts: 1)]
class WorkflowWithGranularMergedOptions
{
    #[WorkflowMethod]
    #[TaskQueue('method-queue')]
    #[RetryOptions(maximumAttempts: 2)]
    public function handle(): void {}
}
