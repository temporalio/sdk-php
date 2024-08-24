<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Activity;

use Spiral\Attributes\AnnotationReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Temporal\Internal\Declaration\Reader\ActivityReader;
use Temporal\Tests\Unit\AbstractUnit;
use WeakReference;

final class ActivityPrototypeTestCase extends AbstractUnit
{
    private ActivityReader $activityReader;

    protected function setUp(): void
    {
        $this->activityReader = new ActivityReader(new SelectiveReader([new AnnotationReader(), new AttributeReader()]));
        parent::setUp();
    }

    public function testInstanceLeaks(): void
    {
        $instance = new DummyActivity();
        $proto = $this->activityReader
            ->fromClass(DummyActivity::class)[0];

        $refProto = WeakReference::create($proto);
        $refInstance = WeakReference::create($proto->getInstance());
        $refHandler = WeakReference::create($proto->getHandler());
        $refInstanceHandler = WeakReference::create($proto->getInstance()->getHandler());
        $refActivity = WeakReference::create($proto->getInstance()->getContext());

        unset($proto, $instance);

        $this->assertNull($refInstanceHandler->get());
        $this->assertNull($refActivity->get());
        $this->assertNull($refProto->get());
        $this->assertNull($refHandler->get());
        $this->assertNull($refInstance->get());
    }

    public function testProtoWithInstanceImmutabilityAndLeaks(): void
    {
        $instance = new DummyActivity();
        $proto = $this->activityReader
            ->fromClass(DummyActivity::class)[0];
        $newProto = $proto->withInstance($instance);
        // References
        $refProto = WeakReference::create($proto);
        $refNewProto = WeakReference::create($newProto);

        // New object is result of clone operation
        $this->assertNotSame($proto, $newProto);

        // There is no leaks after scope destroying
        unset($proto, $newProto);
        $this->assertNull($refProto->get());
        $this->assertNull($refNewProto->get());
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

    public function testGetFactory(): void
    {
        $proto = $this->activityReader->fromClass(DummyActivity::class)[0];
        $protoWithFactory = $proto->withFactory(fn (\ReflectionClass $reflectionClass) => $reflectionClass->newInstance());

        $this->assertNull($proto->getFactory());
        $this->assertNotNull($protoWithFactory->getFactory());
    }
}
