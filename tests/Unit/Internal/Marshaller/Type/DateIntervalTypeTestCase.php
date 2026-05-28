<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use Carbon\CarbonInterval;
use Google\Protobuf\Duration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;

#[CoversClass(DateIntervalType::class)]
final class DateIntervalTypeTestCase extends TestCase
{
    public function testMatchDateInterval(): void
    {
        $type = $this->createReflectionNamedType(\DateInterval::class, false);
        $this->assertTrue(DateIntervalType::match($type));
    }

    public function testMatchCarbonInterval(): void
    {
        $type = $this->createReflectionNamedType(CarbonInterval::class, false);
        $this->assertTrue(DateIntervalType::match($type));
    }

    public function testMatchReturnsFalseForBuiltin(): void
    {
        $type = $this->createReflectionNamedType('int', true);
        $this->assertFalse(DateIntervalType::match($type));
    }

    public function testMatchReturnsFalseForNonDateInterval(): void
    {
        $type = $this->createReflectionNamedType(\stdClass::class, false);
        $this->assertFalse(DateIntervalType::match($type));
    }

    public function testSerializeNanoseconds(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_NANOSECONDS);

        $interval = CarbonInterval::seconds(5);
        $result = $type->serialize($interval);

        $this->assertIsInt($result);
        $this->assertSame(5_000_000_000, $result);
    }

    public function testSerializeSeconds(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_SECONDS);

        $interval = CarbonInterval::minutes(2);
        $result = $type->serialize($interval);

        $this->assertSame(120, $result);
    }

    public function testSerializeMilliseconds(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_MILLISECONDS);

        $interval = CarbonInterval::seconds(3);
        $result = $type->serialize($interval);

        $this->assertSame(3000, $result);
    }

    public function testSerializeMicroseconds(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_MICROSECONDS);

        $interval = CarbonInterval::seconds(1);
        $result = $type->serialize($interval);

        $this->assertSame(1_000_000, $result);
    }

    public function testSerializeMinutes(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_MINUTES);

        $interval = CarbonInterval::hours(1);
        $result = $type->serialize($interval);

        $this->assertSame(60, $result);
    }

    public function testSerializeHours(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_HOURS);

        $interval = CarbonInterval::days(1);
        $result = $type->serialize($interval);

        $this->assertSame(24, $result);
    }

    public function testSerializeDays(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_DAYS);

        $interval = CarbonInterval::weeks(1);
        $result = $type->serialize($interval);

        $this->assertSame(7, $result);
    }

    public function testSerializeWeeks(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_WEEKS);

        $interval = CarbonInterval::weeks(2);
        $result = $type->serialize($interval);

        $this->assertSame(2, $result);
    }

    public function testSerializeDurationFromDateInterval(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, Duration::class);

        $interval = new \DateInterval('PT10S');
        $result = $type->serialize($interval);

        $this->assertInstanceOf(Duration::class, $result);
        $this->assertSame(10, $result->getSeconds());
    }

    public function testSerializeDurationFromCarbonInterval(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, Duration::class);

        $interval = CarbonInterval::seconds(30)->microseconds(500000);
        $result = $type->serialize($interval);

        $this->assertInstanceOf(Duration::class, $result);
        $this->assertSame(30, $result->getSeconds());
        $this->assertSame(500000000, $result->getNanos());
    }

    public function testSerializeDurationFromInt(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, Duration::class);

        $result = $type->serialize(30);

        $this->assertInstanceOf(Duration::class, $result);
        $this->assertSame(30, $result->getSeconds());
        $this->assertSame(0, $result->getNanos());
    }

    public function testSerializeDurationFromString(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, Duration::class);

        $result = $type->serialize('60');

        $this->assertInstanceOf(Duration::class, $result);
        $this->assertSame(60, $result->getSeconds());
    }

    public function testSerializeDurationFromFloat(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, Duration::class);

        $result = $type->serialize(10.5);

        $this->assertInstanceOf(Duration::class, $result);
        $this->assertSame(10, $result->getSeconds());
        $this->assertSame(500000000, $result->getNanos());
    }

    public function testSerializeDurationFromInvalidTypeThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, Duration::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value type.');

        $type->serialize(null);
    }

    public function testSerializeYears(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_YEARS);

        $interval = CarbonInterval::years(3);
        $result = $type->serialize($interval);

        $this->assertSame(3, $result);
    }

    public function testSerializeMonths(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_MONTHS);

        $interval = CarbonInterval::years(1);
        $result = $type->serialize($interval);

        $this->assertSame(12, $result);
    }

    public function testParse(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new DateIntervalType($marshaller, DateInterval::FORMAT_SECONDS);

        $result = $type->parse(60, null);

        $this->assertInstanceOf(CarbonInterval::class, $result);
    }

    public function testMakeRuleReturnsNullForBuiltinType(): void
    {
        $property = $this->createPropertyWithType('int', true, false);
        $this->assertNull(DateIntervalType::makeRule($property));
    }

    public function testMakeRuleReturnsNullForNonDateInterval(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, false);
        $this->assertNull(DateIntervalType::makeRule($property));
    }

    public function testMakeRuleForNonNullable(): void
    {
        $property = $this->createPropertyWithType(\DateInterval::class, false, false);

        $rule = DateIntervalType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(DateIntervalType::class, $rule->type);
    }

    public function testMakeRuleForNullable(): void
    {
        $property = $this->createPropertyWithType(\DateInterval::class, false, true);

        $rule = DateIntervalType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(NullableType::class, $rule->type);
    }

    public function testMakeRuleReturnsNullForNonReflectionNamedType(): void
    {
        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn(null);

        $this->assertNull(DateIntervalType::makeRule($property));
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
