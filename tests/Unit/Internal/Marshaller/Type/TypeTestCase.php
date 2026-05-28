<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Internal\Marshaller\Type\Type;

#[CoversClass(Type::class)]
final class TypeTestCase extends TestCase
{
    public function testOfTypeWithNull(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        // NullableType with null argument creates a type with no inner type
        $type = new NullableType($marshaller, null);

        // Parse should just return the value as-is (no inner type)
        $this->assertSame('value', $type->parse('value', null));
    }

    public function testOfTypeWithTypeInterfaceClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        // ArrayType implements TypeInterface, so ofType should instantiate it directly
        $type = new NullableType($marshaller, ArrayType::class);

        // Non-null value delegates to inner ArrayType
        $this->assertSame([1, 2], $type->parse([1, 2], []));
    }

    public function testOfTypeWithNonTypeClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('unmarshal')->willReturnCallback(
            fn(array $data, object $obj) => (object) $data,
        );

        // stdClass is not a TypeInterface, so ofType wraps it in ObjectType
        $type = new NullableType($marshaller, \stdClass::class);

        $result = $type->parse(['foo' => 'bar'], null);
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame('bar', $result->foo);
    }

    public function testOfTypeWithMarshallingRule(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);

        $rule = new MarshallingRule(type: ArrayType::class);
        $type = new NullableType($marshaller, $rule);

        $this->assertSame([1, 2], $type->parse([1, 2], []));
    }

    public function testOfTypeWithMarshallingRuleNullType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);

        $rule = new MarshallingRule(type: null);
        $type = new NullableType($marshaller, $rule);

        // When rule type is null, ofType returns null, and NullableType has no inner type
        $this->assertSame('value', $type->parse('value', null));
    }

    public function testOfTypeWithMarshallingRuleAndArgs(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('unmarshal')->willReturnCallback(
            fn(array $data, object $obj) => (object) $data,
        );

        // MarshallingRule with of= argument passes it as constructor arg to the type
        $rule = new MarshallingRule(type: ObjectType::class, of: \stdClass::class);
        $type = new NullableType($marshaller, $rule);

        $result = $type->parse(['x' => 1], null);
        $this->assertInstanceOf(\stdClass::class, $result);
    }
}
