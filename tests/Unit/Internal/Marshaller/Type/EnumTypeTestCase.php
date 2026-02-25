<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\EnumType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Tests\Unit\Internal\Marshaller\Type\Stub\IntBackedEnum;
use Temporal\Tests\Unit\Internal\Marshaller\Type\Stub\SimpleEnum;
use Temporal\Tests\Unit\Internal\Marshaller\Type\Stub\StringBackedEnum;

#[CoversClass(EnumType::class)]
final class EnumTypeTestCase extends TestCase
{
    public function testConstructorThrowsWithoutClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Enum is required');

        new EnumType($marshaller, null);
    }

    public function testParseEnumInstance(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, StringBackedEnum::class);

        $result = $type->parse(StringBackedEnum::Foo, null);

        $this->assertSame(StringBackedEnum::Foo, $result);
    }

    public function testParseScalarValueForBackedEnum(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, StringBackedEnum::class);

        $result = $type->parse('foo', null);

        $this->assertSame(StringBackedEnum::Foo, $result);
    }

    public function testParseIntScalarValue(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, IntBackedEnum::class);

        $result = $type->parse(1, null);

        $this->assertSame(IntBackedEnum::One, $result);
    }

    public function testParseArrayWithValueKey(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, StringBackedEnum::class);

        $result = $type->parse(['value' => 'bar'], null);

        $this->assertSame(StringBackedEnum::Bar, $result);
    }

    public function testParseArrayWithNameKey(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, SimpleEnum::class);

        $result = $type->parse(['name' => 'Alpha'], null);

        $this->assertSame(SimpleEnum::Alpha, $result);
    }

    public function testParseArrayWithValueKeyTakesPrecedenceOverName(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, StringBackedEnum::class);

        $result = $type->parse(['value' => 'foo', 'name' => 'Bar'], null);

        $this->assertSame(StringBackedEnum::Foo, $result);
    }

    public function testParseNonScalarNonArrayNonObjectThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, SimpleEnum::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Enum value');

        // null is not scalar, not array, not object matching the enum
        $type->parse(null, null);
    }

    public function testParseScalarOnNonBackedEnumThrowsError(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, SimpleEnum::class);

        // SimpleEnum is non-backed and doesn't have from(), calling from() triggers Error
        $this->expectException(\Error::class);

        $type->parse('Alpha', null);
    }

    public function testParseArrayWithoutValueOrNameThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, SimpleEnum::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Enum value');

        $type->parse(['unknown' => 'x'], null);
    }

    public function testSerializeBackedEnum(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, StringBackedEnum::class);

        $result = $type->serialize(StringBackedEnum::Foo);

        $this->assertSame(['name' => 'Foo', 'value' => 'foo'], $result);
    }

    public function testSerializeSimpleEnum(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EnumType($marshaller, SimpleEnum::class);

        $result = $type->serialize(SimpleEnum::Alpha);

        $this->assertSame(['name' => 'Alpha'], $result);
    }

    public function testMakeRuleReturnsNullForNonEnumType(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, false);
        $this->assertNull(EnumType::makeRule($property));
    }

    public function testMakeRuleReturnsNullForBuiltinType(): void
    {
        $property = $this->createPropertyWithType('string', true, false);
        $this->assertNull(EnumType::makeRule($property));
    }

    public function testMakeRuleForNonNullableEnum(): void
    {
        $property = $this->createPropertyWithType(StringBackedEnum::class, false, false);

        $rule = EnumType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(EnumType::class, $rule->type);
    }

    public function testMakeRuleForNullableEnum(): void
    {
        $property = $this->createPropertyWithType(StringBackedEnum::class, false, true);

        $rule = EnumType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(NullableType::class, $rule->type);
    }

    public function testMakeRuleReturnsNullForNonReflectionNamedType(): void
    {
        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn(null);

        $this->assertNull(EnumType::makeRule($property));
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
