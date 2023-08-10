<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\ObjectType;

use ReflectionClass;
use stdClass;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\ChildDto;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\Nested1;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\Nested2;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\Nested3;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\NestedParent;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\NullableProperty;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\ParentDto;
use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\ReadonlyProperty;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\StdClassObjectProp;

final class ObjectTypeTestCase extends DTOMarshallingTestCase
{
    public function testReflectionTypeMarshal(): void
    {
        $dto = new ParentDto(
            new ChildDto('foo')
        );

        $result = $this->marshal($dto);

        $this->assertEquals(['child' => ['foo' => 'foo']], $result);
    }

    public function testReflectionTypeUnmarshal(): void
    {
        $dto = $this->unmarshal([
            'child' => ['foo' => 'bar'],
        ], (new ReflectionClass(ParentDto::class))->newInstanceWithoutConstructor());

        self::assertEquals(new ParentDto(
            new ChildDto('bar')
        ), $dto);
    }

    public function testReadonlyMarshal(): void
    {
        $dto = new ReadonlyProperty(
            new ChildDto('foo')
        );

        $result = $this->marshal($dto);

        $this->assertEquals(['child' => ['foo' => 'foo']], $result);
    }

    public function testNullableObjectMarshal(): void
    {
        $dto = new NullableProperty(null);

        $result = $this->marshal($dto);

        $this->assertEquals(['child' => null], $result);
    }

    public function testNullableObjectUnmarshal(): void
    {
        $dto = $this->unmarshal([
            'child' => null,
        ], (new ReflectionClass(NullableProperty::class))->newInstanceWithoutConstructor());

        self::assertEquals(new NullableProperty(null), $dto);
    }

    public function testStdClassParamUnmarshal(): void
    {
        $dto = $this->unmarshal([
            'object' => ['foo' => 'bar'],
            'class' => ['foo' => 'bar'],
        ], (new ReflectionClass(StdClassObjectProp::class))->newInstanceWithoutConstructor());

        self::assertEquals(new StdClassObjectProp(
            (object)['foo' => 'bar'],
            (object)['foo' => 'bar'],
        ), $dto);
    }

    public function testStdClassUnmarshal(): void
    {
        $dto = $this->unmarshal([
            'object' => ['foo' => 'bar'],
            'class' => ['foo' => 'bar'],
        ], new stdClass());

        self::assertEquals((object)[
            'object' => ['foo' => 'bar'],
            'class' => ['foo' => 'bar'],
        ], $dto);
    }

    public function testReadonlyUnmarshal(): void
    {
        $dto = $this->unmarshal([
            'child' => ['foo' => 'bar'],
        ], (new ReflectionClass(ReadonlyProperty::class))->newInstanceWithoutConstructor());

        self::assertEquals(new ReadonlyProperty(
            new ChildDto('bar')
        ), $dto);
    }

    public function testNestedMarshal(): void
    {
        $dto = new NestedParent(
            new Nested1(new Nested2(new Nested3('bar')))
        );

        $marshal = $this->marshal($dto);

        $this->assertSame(['child' => ['child' => ['child' => ['value' => 'bar']]]], $marshal);
    }

    public function testNestedUnmarshal(): void
    {
        $dto = new NestedParent(
            new Nested1(new Nested2(new Nested3('bar')))
        );

        $unmarshal = $this->unmarshal(
            ['child' => ['child' => ['child' => ['value' => 'bar']]]],
            (new ReflectionClass(NestedParent::class))->newInstanceWithoutConstructor(),
        );

        $this->assertEquals($dto, $unmarshal);
    }

    protected function getTypeMatchers(): array
    {
        return [
            ObjectType::class,
        ];
    }
}
