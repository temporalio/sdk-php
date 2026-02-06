<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use Temporal\Activity\ActivityId;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Activity\ActivityPriority;
use Temporal\Activity\CancellationType;
use Temporal\Activity\HeartbeatTimeout;
use Temporal\Activity\RetryPolicy;
use Temporal\Activity\ScheduleToCloseTimeout;
use Temporal\Activity\ScheduleToStartTimeout;
use Temporal\Activity\StartToCloseTimeout;
use Temporal\Activity\Summary;
use Temporal\Activity\TaskQueue;
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
        $this->assertSame('my-id', $options->activityId);
        $this->assertSame('my-summary', $options->summary);
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
    }
}

#[ActivityInterface]
#[TaskQueue('class-queue')]
#[ScheduleToCloseTimeout(10)]
#[ScheduleToStartTimeout(5)]
#[RetryPolicy(new RetryOptions(maximumAttempts: 1))]
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
    #[ActivityId('my-id')]
    #[Summary('my-summary')]
    public function handle(): void {}
}

#[ActivityInterface]
#[TaskQueue('class-queue')]
#[ScheduleToCloseTimeout(10)]
#[RetryPolicy(new RetryOptions(maximumAttempts: 1))]
class ActivityWithGranularMergedOptions
{
    #[ActivityMethod]
    #[TaskQueue('method-queue')]
    #[RetryPolicy(new RetryOptions(maximumAttempts: 2))]
    #[ActivityPriority(new Priority(priorityKey: 7))]
    public function handle(): void {}
}
