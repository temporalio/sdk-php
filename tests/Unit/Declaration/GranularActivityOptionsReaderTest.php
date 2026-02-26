<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use Temporal\Activity\ActivityCancellationType;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\Attribute\CancellationType;
use Temporal\Activity\Attribute\ScheduleToCloseTimeout;
use Temporal\Activity\Attribute\ScheduleToStartTimeout;
use Temporal\Activity\Attribute\StartToCloseTimeout;
use Temporal\Activity\Attribute\Summary;
use Temporal\Activity\Attribute\TaskQueue;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Spiral\Attributes\AttributeReader;
use Temporal\Common\RetryOptions;
use Temporal\Common\Priority;

/**
 * @group unit
 * @group declaration
 */
class GranularActivityOptionsReaderTest extends AbstractDeclaration
{
    public function testTaskQueueFromAttribute(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithTaskQueueOnly::class);

        $this->assertCount(1, $protos);
        $options = $protos[0]->getMethodOptions();

        $this->assertInstanceOf(ActivityOptions::class, $options);
        $this->assertSame('custom-activity-queue', $options->taskQueue);
    }

    public function testTaskQueueFromMethodOverridesClass(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithTaskQueueOverride::class);

        $this->assertCount(1, $protos);
        $options = $protos[0]->getMethodOptions();

        $this->assertInstanceOf(ActivityOptions::class, $options);
        $this->assertSame('method-override-queue', $options->taskQueue);
    }

    public function testGranularOptionsFromClass(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithGranularOptionsOnClass::class);

        $this->assertCount(1, $protos);
        $proto = $protos[0];

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(ActivityOptions::class, $options);
        $this->assertSame('class-queue', $options->taskQueue);
        $this->assertSame(10, $options->scheduleToCloseTimeout->s);
        $this->assertSame(5, $options->scheduleToStartTimeout->s);
        $this->assertSame(1, $options->retryOptions->maximumAttempts);
        $this->assertSame(ActivityCancellationType::Abandon->value, $options->cancellationType);
    }

    public function testGranularOptionsFromMethod(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithGranularOptionsOnMethod::class);

        $this->assertCount(1, $protos);
        $proto = $protos[0];

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(ActivityOptions::class, $options);
        $this->assertSame('method-queue', $options->taskQueue);
        $this->assertSame(20, $options->startToCloseTimeout->s);
        $this->assertSame('my-summary', $options->summary);
        $this->assertSame(ActivityCancellationType::WaitCancellationCompleted->value, $options->cancellationType);
    }

    public function testGranularOptionsMerged(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithGranularMergedOptions::class);

        $this->assertCount(1, $protos);
        $proto = $protos[0];

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(ActivityOptions::class, $options);
        $this->assertSame('method-queue', $options->taskQueue); // Overridden by method
        $this->assertSame(10, $options->scheduleToCloseTimeout->s); // Inherited from class
        $this->assertSame(2, $options->retryOptions->maximumAttempts); // Overridden by method
        $this->assertSame(7, $options->priority->priorityKey); // From method
        $this->assertSame(ActivityCancellationType::Abandon->value, $options->cancellationType); // Inherited from class
    }
}

#[ActivityInterface]
#[TaskQueue('class-queue')]
#[ScheduleToCloseTimeout(10)]
#[ScheduleToStartTimeout(5)]
#[RetryOptions(maximumAttempts: 1)]
#[CancellationType(ActivityCancellationType::Abandon)]
class ActivityWithGranularOptionsOnClass
{
    #[ActivityMethod]
    public function handle(): void {}
}

#[ActivityInterface]
class ActivityWithGranularOptionsOnMethod
{
    #[ActivityMethod]
    #[TaskQueue('method-queue')]
    #[StartToCloseTimeout(20)]
    #[Summary('my-summary')]
    #[CancellationType(ActivityCancellationType::WaitCancellationCompleted)]
    public function handle(): void {}
}

#[ActivityInterface]
#[TaskQueue('custom-activity-queue')]
class ActivityWithTaskQueueOnly
{
    #[ActivityMethod]
    public function handle(): void {}
}

#[ActivityInterface]
#[TaskQueue('class-level-queue')]
class ActivityWithTaskQueueOverride
{
    #[ActivityMethod]
    #[TaskQueue('method-override-queue')]
    public function handle(): void {}
}

#[ActivityInterface]
#[TaskQueue('class-queue')]
#[ScheduleToCloseTimeout(10)]
#[RetryOptions(maximumAttempts: 1)]
#[CancellationType(ActivityCancellationType::Abandon)]
class ActivityWithGranularMergedOptions
{
    #[ActivityMethod]
    #[TaskQueue('method-queue')]
    #[RetryOptions(maximumAttempts: 2)]
    #[Priority(priorityKey: 7)]
    public function handle(): void {}
}
