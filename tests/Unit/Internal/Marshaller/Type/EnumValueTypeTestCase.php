<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\EnumValueType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Tests\Unit\Internal\Marshaller\Type\Stub\IntBackedEnum;
use Temporal\Tests\Unit\Internal\Marshaller\Type\Stub\SimpleEnum;
use Temporal\Tests\Unit\Internal\Marshaller\Type\Stub\StringBackedEnum;

#[CoversClass(EnumValueType::class)]
final class EnumValueTypeTestCase extends TestCase
{
    public function testConstructorThrowsWithoutClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Enum is required');

        new EnumValueType($marshaller, null);
    }

    public function testConstructorThrowsForNonBackedEnum(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be an instance of BackedEnum');

        new EnumValueType($marshaller, SimpleEnum::class);
    }

    public function testParseBackedEnumInstance(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumValueType($marshaller, StringBackedEnum::class);

        $result = $type->parse(StringBackedEnum::Foo, null);

        $this->assertSame(StringBackedEnum::Foo, $result);
    }

    public function testParseStringValue(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumValueType($marshaller, StringBackedEnum::class);

        $result = $type->parse('bar', null);

        $this->assertSame(StringBackedEnum::Bar, $result);
    }

    public function testParseIntValue(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumValueType($marshaller, IntBackedEnum::class);

        $result = $type->parse(2, null);

        $this->assertSame(IntBackedEnum::Two, $result);
    }

    public function testParseInvalidTypeThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumValueType($marshaller, StringBackedEnum::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Enum value');

        $type->parse(12.5, null);
    }

    public function testParseArrayThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumValueType($marshaller, StringBackedEnum::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Enum value');

        $type->parse(['value' => 'foo'], null);
    }

    public function testSerializeStringBacked(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumValueType($marshaller, StringBackedEnum::class);

        $result = $type->serialize(StringBackedEnum::Foo);

        $this->assertSame('foo', $result);
    }

    public function testSerializeIntBacked(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumValueType($marshaller, IntBackedEnum::class);

        $result = $type->serialize(IntBackedEnum::One);

        $this->assertSame(1, $result);
    }

    public function testMakeRuleReturnsNullForNonEnumType(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, false);
        $this->assertNull(EnumValueType::makeRule($property));
    }

    public function testMakeRuleReturnsNullForBuiltinType(): void
    {
        $property = $this->createPropertyWithType('string', true, false);
        $this->assertNull(EnumValueType::makeRule($property));
    }

    public function testMakeRuleForNonNullableEnum(): void
    {
        $property = $this->createPropertyWithType(StringBackedEnum::class, false, false);

        $rule = EnumValueType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(EnumValueType::class, $rule->type);
    }

    public function testMakeRuleForNullableEnum(): void
    {
        $property = $this->createPropertyWithType(StringBackedEnum::class, false, true);

        $rule = EnumValueType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(NullableType::class, $rule->type);
    }

    public function testMakeRuleReturnsNullForNonReflectionNamedType(): void
    {
        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn(null);

        $this->assertNull(EnumValueType::makeRule($property));
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
