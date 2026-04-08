<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\ObjectType;

#[CoversClass(ObjectType::class)]
final class ObjectTypeTestCase extends TestCase
{
    public function testMatchBuiltinObject(): void
    {
        $type = $this->createReflectionNamedType('object', true);
        $this->assertTrue(ObjectType::match($type));
    }

    public function testMatchNonBuiltinClass(): void
    {
        $type = $this->createReflectionNamedType(\stdClass::class, false);
        $this->assertTrue(ObjectType::match($type));
    }

    public function testMatchReturnsFalseForBuiltinNonObject(): void
    {
        $type = $this->createReflectionNamedType('string', true);
        $this->assertFalse(ObjectType::match($type));
    }

    public function testParseObjectInstanceOf(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ObjectType($marshaller, \stdClass::class);

        $obj = (object) ['foo' => 'bar'];
        $result = $type->parse($obj, null);

        $this->assertSame($obj, $result);
    }

    public function testParseArrayIntoStdClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ObjectType($marshaller, \stdClass::class);

        $result = $type->parse(['foo' => 'bar'], null);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('bar', $result->foo);
    }

    public function testParseArrayIntoStdClassWithCurrent(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ObjectType($marshaller, \stdClass::class);

        $current = (object) ['existing' => 'value'];
        $result = $type->parse(['foo' => 'bar'], $current);

        $this->assertSame('bar', $result->foo);
    }

    public function testParseArrayIntoTypedObjectUsesMarshaller(): void
    {
        $target = new class {
            public string $foo = '';
        };
        $targetClass = $target::class;

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('unmarshal')
            ->with(['foo' => 'bar'], $this->isInstanceOf($targetClass))
            ->willReturnCallback(function (array $data, object $obj) {
                $obj->foo = $data['foo'];
                return $obj;
            });

        $type = new ObjectType($marshaller, $targetClass);
        $result = $type->parse(['foo' => 'bar'], null);

        $this->assertSame('bar', $result->foo);
    }

    public function testParseNullIntoTypedObject(): void
    {
        $target = new class {
            public string $foo = 'default';
        };
        $targetClass = $target::class;

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturnArgument(1);

        $type = new ObjectType($marshaller, $targetClass);
        $result = $type->parse(null, null);

        $this->assertInstanceOf($targetClass, $result);
    }

    public function testSerializeStdClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ObjectType($marshaller, \stdClass::class);

        $result = $type->serialize((object) ['a' => 1, 'b' => 2]);

        $this->assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testSerializeTypedObjectUsesMarshaller(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('marshal')
            ->willReturn(['foo' => 'bar']);

        $target = new class {
            public string $foo = 'bar';
        };
        $type = new ObjectType($marshaller, $target::class);

        $result = $type->serialize($target);

        $this->assertSame(['foo' => 'bar'], $result);
    }

    public function testMakeRuleReturnsNullForBuiltinType(): void
    {
        $property = $this->createPropertyWithType('string', true, false);
        $this->assertNull(ObjectType::makeRule($property));
    }

    public function testMakeRuleForNonNullableObject(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, false);

        $rule = ObjectType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(ObjectType::class, $rule->type);
    }

    public function testMakeRuleForNullableObject(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, true);

        $rule = ObjectType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(NullableType::class, $rule->type);
    }

    public function testMakeRuleReturnsNullForNonReflectionNamedType(): void
    {
        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn(null);

        $this->assertNull(ObjectType::makeRule($property));
    }

    public function testDefaultClassIsStdClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ObjectType($marshaller);

        $result = $type->parse(['x' => 1], null);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame(1, $result->x);
    }

    public function testParseWithExistingTypedObject(): void
    {
        $target = new class {
            public string $foo = '';
        };

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturnCallback(function (array $data, object $obj) {
                $obj->foo = $data['foo'];
                return $obj;
            });

        $type = new ObjectType($marshaller, $target::class);
        $existing = clone $target;
        $result = $type->parse(['foo' => 'updated'], $existing);

        $this->assertSame('updated', $result->foo);
    }

    public function testDeprecatedInstanceMethodWithStdClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ObjectType($marshaller, \stdClass::class);

        $method = new \ReflectionMethod($type, 'instance');
        $result = $method->invoke($type, ['a' => 1, 'b' => 2]);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame(1, $result->a);
        $this->assertSame(2, $result->b);
    }

    public function testDeprecatedInstanceMethodWithTypedClass(): void
    {
        $target = new class {
            public string $foo = '';
        };
        $targetClass = $target::class;

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturnCallback(function (array $data, object $obj) {
                $obj->foo = $data['foo'];
                return $obj;
            });

        $type = new ObjectType($marshaller, $targetClass);

        $method = new \ReflectionMethod($type, 'instance');
        $result = $method->invoke($type, ['foo' => 'bar']);

        $this->assertInstanceOf($targetClass, $result);
        $this->assertSame('bar', $result->foo);
    }

    private function createReflectionNamedType(string $name, bool $isBuiltin): \ReflectionNamedType
    {
        $type = $this->createMock(\ReflectionNamedType::class);
        $type->method('getName')->willReturn($name);
        $type->method('isBuiltin')->willReturn($isBuiltin);
        return $type;
    }

    private function createPropertyWithType(string $typeName, bool $isBuiltin, bool $allowsNull): \ReflectionProperty
    {
        $type = $this->createMock(\ReflectionNamedType::class);
        $type->method('getName')->willReturn($typeName);
        $type->method('isBuiltin')->willReturn($isBuiltin);
        $type->method('allowsNull')->willReturn($allowsNull);

        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn($type);
        $property->method('getName')->willReturn('test');

        return $property;
    }
}
