<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Marshaller\Type\ArrayType;

#[CoversClass(ArrayType::class)]
final class ArrayTypeTestCase extends TestCase
{
    public function testMatchArray(): void
    {
        $type = $this->createReflectionNamedType('array');
        $this->assertTrue(ArrayType::match($type));
    }

    public function testMatchIterable(): void
    {
        $type = $this->createReflectionNamedType('iterable');
        $this->assertTrue(ArrayType::match($type));
    }

    public function testMatchReturnsFalseForString(): void
    {
        $type = $this->createReflectionNamedType('string');
        $this->assertFalse(ArrayType::match($type));
    }

    public function testParseNullReturnsEmptyArray(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ArrayType($marshaller);

        $this->assertSame([], $type->parse(null, []));
    }

    public function testParseInvalidTypeThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ArrayType($marshaller);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a type of array');

        $type->parse('not an array', []);
    }

    public function testParseArrayWithoutInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ArrayType($marshaller);

        $this->assertSame([1, 2, 3], $type->parse([1, 2, 3], []));
    }

    public function testParseArrayWithInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('unmarshal')->willReturnCallback(
            fn(array $data, object $obj) => (object) $data,
        );
        $type = new ArrayType($marshaller, \stdClass::class);

        $result = $type->parse([['a' => 1], ['b' => 2]], []);

        $this->assertCount(2, $result);
        $this->assertEquals((object) ['a' => 1], $result[0]);
        $this->assertEquals((object) ['b' => 2], $result[1]);
    }

    public function testSerializeArrayWithoutInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ArrayType($marshaller);

        $this->assertSame([1, 2, 3], $type->serialize([1, 2, 3]));
    }

    public function testSerializeWithInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('marshal')->willReturnCallback(
            fn(object $obj) => (array) $obj,
        );
        $type = new ArrayType($marshaller, \stdClass::class);

        $result = $type->serialize([(object) ['a' => 1]]);

        $this->assertSame([['a' => 1]], $result);
    }

    public function testSerializeIterableWithoutInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new ArrayType($marshaller);

        $generator = (static function () {
            yield 'a';
            yield 'b';
        })();

        $this->assertSame(['a', 'b'], $type->serialize($generator));
    }

    public function testMakeRuleReturnsNullForNonArrayType(): void
    {
        $property = $this->createPropertyWithType('string', true, false);

        $this->assertNull(ArrayType::makeRule($property));
    }

    public function testMakeRuleForNonNullableArray(): void
    {
        $property = $this->createPropertyWithType('array', true, false);

        $rule = ArrayType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(ArrayType::class, $rule->type);
    }

    public function testMakeRuleForNullableArray(): void
    {
        $property = $this->createPropertyWithType('array', true, true);

        $rule = ArrayType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(\Temporal\Internal\Marshaller\Type\NullableType::class, $rule->type);
    }

    public function testMakeRuleForIterableType(): void
    {
        $property = $this->createPropertyWithType('iterable', true, false);

        $rule = ArrayType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(ArrayType::class, $rule->type);
    }

    public function testMakeRuleReturnsNullForNonReflectionNamedType(): void
    {
        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn(null);

        $this->assertNull(ArrayType::makeRule($property));
    }

    public function testConstructWithMarshallingRule(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('unmarshal')->willReturnCallback(
            fn(array $data, object $obj) => (object) $data,
        );

        $rule = new MarshallingRule(type: \stdClass::class);
        $type = new ArrayType($marshaller, $rule);

        $result = $type->parse([['x' => 1]], []);
        $this->assertEquals((object) ['x' => 1], $result[0]);
    }

    private function createReflectionNamedType(string $name): \ReflectionNamedType
    {
        $type = $this->createMock(\ReflectionNamedType::class);
        $type->method('getName')->willReturn($name);
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
