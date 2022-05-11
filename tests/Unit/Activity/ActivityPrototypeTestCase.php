<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Activity;

use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Tests\Unit\UnitTestCase;

final class ActivityPrototypeTestCase extends UnitTestCase
{
    private ActivityReader $activityReader;

    protected function setUp(): void
    {
        $this->activityReader = new ActivityReader(new SelectiveReader([new AnnotationReader(), new AttributeReader()]));
        parent::setUp();
    }


    public function testGetInstanceFromObject(): void
    {
        $instance = new DummyActivity();
        $proto = $this->activityReader->fromClass(DummyActivity::class)[0];
        $proto = $proto->withInstance($instance);

        self::assertSame($instance, $proto->getInstance()->getContext());
    }

    public function testGetInstanceFromClass(): void
    {
        $proto = $this->activityReader->fromClass(DummyActivity::class)[0];

        self::assertInstanceOf(DummyActivity::class, $proto->getInstance()->getContext());
    }

    public function testGetInstanceFromFactory(): void
    {
        $proto = $this->activityReader->fromClass(DummyActivity::class)[0];
        $protoWithFactory = $proto->withFactory(fn () => new DummyActivity());

        $this->assertInstanceOf(DummyActivity::class, $protoWithFactory->getInstance()->getContext());
    }

    public function testLocalActivityFlag(): void
    {
        $proto = $this->activityReader->fromClass(DummyActivity::class)[0];
        self::assertFalse($proto->isLocalActivity());

        $proto = $this->activityReader->fromClass(DummyLocalActivity::class)[0];
        self::assertTrue($proto->isLocalActivity());
    }

    public function testFactoryCreatesNewInstances(): void
    {
        $proto = $this->activityReader->fromClass(DummyActivity::class)[0];
        $protoWithFactory = $proto->withFactory(fn () => new DummyActivity());

        $this->assertEquals($protoWithFactory->getInstance()->getContext(), $protoWithFactory->getInstance()->getContext());
        $this->assertNotSame($protoWithFactory->getInstance()->getContext(), $protoWithFactory->getInstance()->getContext());
    }

    public function testFactoryAcceptsReflectionClassOfActivity(): void
    {
        $proto = $this->activityReader->fromClass(DummyActivity::class)[0];
        $protoWithFactory = $proto->withFactory(fn (\ReflectionClass $reflectionClass) => $reflectionClass->newInstance());

        $this->assertEquals($protoWithFactory->getInstance()->getContext(), $protoWithFactory->getInstance()->getContext());
        $this->assertNotSame($protoWithFactory->getInstance()->getContext(), $protoWithFactory->getInstance()->getContext());
    }
}
