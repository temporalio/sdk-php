<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\ArrayType;

#[CoversClass(NullableType::class)]
final class NullableTypeTestCase extends TestCase
{
    public function testParseNullReturnsNull(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new NullableType($marshaller);

        $this->assertNull($type->parse(null, null));
    }

    public function testParseNonNullWithoutInnerTypeReturnsValue(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new NullableType($marshaller);

        $this->assertSame('hello', $type->parse('hello', null));
    }

    public function testParseNonNullWithInnerTypeDelegates(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new NullableType($marshaller, ArrayType::class);

        $this->assertSame([1, 2, 3], $type->parse([1, 2, 3], []));
    }

    public function testSerializeNullReturnsNull(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new NullableType($marshaller);

        $this->assertNull($type->serialize(null));
    }

    public function testSerializeNonNullWithoutInnerTypeReturnsValue(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new NullableType($marshaller);

        $this->assertSame('hello', $type->serialize('hello'));
    }

    public function testSerializeNonNullWithInnerTypeDelegates(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new NullableType($marshaller, ArrayType::class);

        $this->assertSame([1, 2], $type->serialize([1, 2]));
    }
}
