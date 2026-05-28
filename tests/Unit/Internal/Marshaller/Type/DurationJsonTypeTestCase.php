<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use Carbon\CarbonInterval;
use Google\Protobuf\Duration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\DurationJsonType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;

#[CoversClass(DurationJsonType::class)]
final class DurationJsonTypeTestCase extends TestCase
{
    public function testMatchDateInterval(): void
    {
        $type = $this->createReflectionNamedType(\DateInterval::class, false);
        $this->assertTrue(DurationJsonType::match($type));
    }

    public function testMatchCarbonInterval(): void
    {
        $type = $this->createReflectionNamedType(CarbonInterval::class, false);
        $this->assertTrue(DurationJsonType::match($type));
    }

    public function testMatchReturnsFalseForBuiltin(): void
    {
        $type = $this->createReflectionNamedType('int', true);
        $this->assertFalse(DurationJsonType::match($type));
    }

    public function testMatchReturnsFalseForNonDateInterval(): void
    {
        $type = $this->createReflectionNamedType(\stdClass::class, false);
        $this->assertFalse(DurationJsonType::match($type));
    }

    public function testSerializeDateInterval(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DurationJsonType($marshaller);

        $interval = CarbonInterval::seconds(5)->microseconds(500000);
        $result = $type->serialize($interval);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('seconds', $result);
        $this->assertArrayHasKey('nanos', $result);
    }

    public function testSerializeInt(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DurationJsonType($marshaller);

        $result = $type->serialize(30);

        $this->assertIsArray($result);
        $this->assertSame(30, $result['seconds']);
        $this->assertSame(0, $result['nanos']);
    }

    public function testSerializeString(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DurationJsonType($marshaller);

        $result = $type->serialize('60');

        $this->assertIsArray($result);
        $this->assertSame(60, $result['seconds']);
    }

    public function testSerializeFloat(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DurationJsonType($marshaller);

        $result = $type->serialize(10.5);

        $this->assertIsArray($result);
        $this->assertSame(10, $result['seconds']);
        $this->assertSame(500000000, $result['nanos']);
    }

    public function testSerializeInvalidTypeThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DurationJsonType($marshaller);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value type');

        $type->serialize(null);
    }

    public function testParseArrayWithSecondsAndNanos(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DurationJsonType($marshaller);

        $result = $type->parse(['seconds' => 5, 'nanos' => 500000000], null);

        $this->assertInstanceOf(CarbonInterval::class, $result);
    }

    public function testParseNull(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DurationJsonType($marshaller);

        $result = $type->parse(null, null);

        $this->assertInstanceOf(CarbonInterval::class, $result);
    }

    public function testParseFallbackFormat(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DurationJsonType($marshaller, DateInterval::FORMAT_SECONDS);

        $result = $type->parse(60, null);

        $this->assertInstanceOf(CarbonInterval::class, $result);
    }

    public function testMakeRuleReturnsNullForBuiltinType(): void
    {
        $property = $this->createPropertyWithType('int', true, false);
        $this->assertNull(DurationJsonType::makeRule($property));
    }

    public function testMakeRuleReturnsNullForNonDateInterval(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, false);
        $this->assertNull(DurationJsonType::makeRule($property));
    }

    public function testMakeRuleForNonNullable(): void
    {
        $property = $this->createPropertyWithType(\DateInterval::class, false, false);

        $rule = DurationJsonType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(DurationJsonType::class, $rule->type);
    }

    public function testMakeRuleForNullable(): void
    {
        $property = $this->createPropertyWithType(\DateInterval::class, false, true);

        $rule = DurationJsonType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(NullableType::class, $rule->type);
    }

    public function testMakeRuleReturnsNullForNonReflectionNamedType(): void
    {
        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn(null);

        $this->assertNull(DurationJsonType::makeRule($property));
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
