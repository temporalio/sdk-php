<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Spiral\Attributes\AttributeReader;

/**
 * @group unit
 * @group declaration
 */
class ActivityOptionsReaderTest extends AbstractDeclaration
{
    public function testActivityOptionsFromClass(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithOptionsOnClass::class);

        $this->assertCount(1, $protos);
        $proto = $protos[0];

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(ActivityOptions::class, $options);
        $this->assertSame('class-queue', $options->taskQueue);
        $this->assertSame(10, $options->scheduleToCloseTimeout->s);
    }

    public function testActivityOptionsFromMethod(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithOptionsOnMethod::class);

        $this->assertCount(1, $protos);
        $proto = $protos[0];

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(ActivityOptions::class, $options);
        $this->assertSame('method-queue', $options->taskQueue);
        $this->assertSame(20, $options->scheduleToCloseTimeout->s);
    }

    public function testActivityOptionsMerged(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithMergedOptions::class);

        $this->assertCount(1, $protos);
        $proto = $protos[0];

        $options = $proto->getMethodOptions();
        $this->assertInstanceOf(ActivityOptions::class, $options);
        $this->assertSame('method-queue', $options->taskQueue); // Overridden by method
        $this->assertSame(10, $options->scheduleToCloseTimeout->s); // Inherited from class
    }

    public function testActivityProxyMerging(): void
    {
        $reader = new ActivityReader(new AttributeReader());
        $protos = $reader->fromClass(ActivityWithMergedOptions::class);
        $proto = $protos[0];

        // Опции из атрибутов
        $attrOptions = $proto->getMethodOptions(); // taskQueue: method-queue, scheduleToCloseTimeout: 10

        // Опции из кода (newActivityStub)
        $userOptions = ActivityOptions::new()->withScheduleToCloseTimeout(5);

        // Логика из ActivityProxy: дефолты из атрибутов мерджим с опциями пользователя
        $finalOptions = ($attrOptions ?? ActivityOptions::new())->mergeWithOptions($userOptions);

        $this->assertSame('method-queue', $finalOptions->taskQueue);
        $this->assertSame(5, $finalOptions->scheduleToCloseTimeout->s);
    }
}

#[ActivityInterface]
#[ActivityOptions(taskQueue: 'class-queue', scheduleToCloseTimeout: 10)]
class ActivityWithOptionsOnClass
{
    #[ActivityMethod]
    public function handle(): void {}
}

#[ActivityInterface]
class ActivityWithOptionsOnMethod
{
    #[ActivityMethod]
    #[ActivityOptions(taskQueue: 'method-queue', scheduleToCloseTimeout: 20)]
    public function handle(): void {}
}

#[ActivityInterface]
#[ActivityOptions(taskQueue: 'class-queue', scheduleToCloseTimeout: 10)]
class ActivityWithMergedOptions
{
    #[ActivityMethod]
    #[ActivityOptions(taskQueue: 'method-queue')]
    public function handle(): void {}
}
