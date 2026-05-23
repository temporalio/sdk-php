<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use Google\Protobuf\Timestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\DateTimeType;
use Temporal\Internal\Marshaller\Type\NullableType;

#[CoversClass(DateTimeType::class)]
final class DateTimeTypeTestCase extends TestCase
{
    public function testMatchDateTimeImmutable(): void
    {
        $type = $this->createReflectionNamedType(\DateTimeImmutable::class, false);
        $this->assertTrue(DateTimeType::match($type));
    }

    public function testMatchDateTime(): void
    {
        $type = $this->createReflectionNamedType(\DateTime::class, false);
        $this->assertTrue(DateTimeType::match($type));
    }

    public function testMatchReturnsFalseForBuiltin(): void
    {
        $type = $this->createReflectionNamedType('string', true);
        $this->assertFalse(DateTimeType::match($type));
    }

    public function testMatchReturnsFalseForNonDateTime(): void
    {
        $type = $this->createReflectionNamedType(\stdClass::class, false);
        $this->assertFalse(DateTimeType::match($type));
    }

    public function testParseString(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateTimeType($marshaller);

        $result = $type->parse('2020-01-01T00:00:00+00:00', null);
        $this->assertInstanceOf(\DateTimeInterface::class, $result);
        $this->assertSame('2020-01-01', $result->format('Y-m-d'));
    }

    public function testSerializeDefaultFormat(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateTimeType($marshaller);

        $result = $type->serialize(new \DateTimeImmutable('2020-01-01T12:00:00+00:00'));
        $this->assertIsString($result);
        $this->assertSame('2020-01-01T12:00:00+00:00', $result);
    }

    public function testSerializeTimestampFormat(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateTimeType($marshaller, format: Timestamp::class);

        $dt = new \DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $result = $type->serialize($dt);

        $this->assertInstanceOf(Timestamp::class, $result);
        $this->assertSame($dt->getTimestamp(), $result->getSeconds());
    }

    public function testSerializeCustomFormat(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateTimeType($marshaller, format: 'Y-m-d');

        $result = $type->serialize(new \DateTimeImmutable('2020-06-15T12:00:00+00:00'));
        $this->assertSame('2020-06-15', $result);
    }

    public function testMakeRuleReturnsNullForNonDateTimeType(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, false);

        $this->assertNull(DateTimeType::makeRule($property));
    }

    public function testMakeRuleReturnsNullForBuiltinType(): void
    {
        $property = $this->createPropertyWithType('string', true, false);

        $this->assertNull(DateTimeType::makeRule($property));
    }

    public function testMakeRuleForNonNullableDateTime(): void
    {
        $property = $this->createPropertyWithType(\DateTimeImmutable::class, false, false);

        $rule = DateTimeType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(DateTimeType::class, $rule->type);
    }

    public function testMakeRuleForNullableDateTime(): void
    {
        $property = $this->createPropertyWithType(\DateTimeImmutable::class, false, true);

        $rule = DateTimeType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(NullableType::class, $rule->type);
    }

    public function testMakeRuleReturnsNullForNonReflectionNamedType(): void
    {
        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn(null);

        $this->assertNull(DateTimeType::makeRule($property));
    }

    public function testParseWithSpecificClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateTimeType($marshaller, \DateTimeImmutable::class);

        $result = $type->parse('2021-01-01T00:00:00+00:00', null);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
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
