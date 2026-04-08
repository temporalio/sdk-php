<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Ramsey\Uuid\UuidInterface;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\UuidType;

#[CoversClass(UuidType::class)]
final class UuidTypeTestCase extends TestCase
{
    public function testMatchUuidInterface(): void
    {
        $type = $this->createReflectionNamedType(UuidInterface::class, false);
        $this->assertTrue(UuidType::match($type));
    }

    public function testMatchUuidV4(): void
    {
        $type = $this->createReflectionNamedType(UuidV4::class, false);
        $this->assertTrue(UuidType::match($type));
    }

    public function testMatchReturnsFalseForBuiltin(): void
    {
        $type = $this->createReflectionNamedType('string', true);
        $this->assertFalse(UuidType::match($type));
    }

    public function testMatchReturnsFalseForNonUuid(): void
    {
        $type = $this->createReflectionNamedType(\stdClass::class, false);
        $this->assertFalse(UuidType::match($type));
    }

    public function testParse(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new UuidType($marshaller);

        $result = $type->parse('d1fb065d-f118-477d-a62a-ef93dc7ee03f', null);

        $this->assertInstanceOf(UuidInterface::class, $result);
        $this->assertSame('d1fb065d-f118-477d-a62a-ef93dc7ee03f', $result->toString());
    }

    public function testSerialize(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new UuidType($marshaller);

        $uuid = UuidV4::fromString('d1fb065d-f118-477d-a62a-ef93dc7ee03f');
        $result = $type->serialize($uuid);

        $this->assertSame('d1fb065d-f118-477d-a62a-ef93dc7ee03f', $result);
    }

    public function testMakeRuleReturnsNullForNonUuid(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, false);
        $this->assertNull(UuidType::makeRule($property));
    }

    public function testMakeRuleForNonNullableUuid(): void
    {
        $property = $this->createPropertyWithType(UuidInterface::class, false, false);

        $rule = UuidType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(UuidType::class, $rule->type);
    }

    public function testMakeRuleForNullableUuid(): void
    {
        $property = $this->createPropertyWithType(UuidInterface::class, false, true);

        $rule = UuidType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(NullableType::class, $rule->type);
    }

    public function testMakeRuleReturnsNullForBuiltin(): void
    {
        $property = $this->createPropertyWithType('string', true, false);
        $this->assertNull(UuidType::makeRule($property));
    }

    public function testMakeRuleReturnsNullForNonReflectionNamedType(): void
    {
        $property = $this->createMock(\ReflectionProperty::class);
        $property->method('getType')->willReturn(null);

        $this->assertNull(UuidType::makeRule($property));
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
