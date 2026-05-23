<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\AssocArrayType;

#[CoversClass(AssocArrayType::class)]
final class AssocArrayTypeTestCase extends TestCase
{
    public function testParseArrayWithoutInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new AssocArrayType($marshaller);

        $this->assertSame(['a' => 1, 'b' => 2], $type->parse(['a' => 1, 'b' => 2], null));
    }

    public function testParseNonArrayThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new AssocArrayType($marshaller);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a type of array');

        $type->parse('not an array', null);
    }

    public function testParseWithInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('unmarshal')->willReturnCallback(
            fn(array $data, object $obj) => (object) $data,
        );
        $type = new AssocArrayType($marshaller, \stdClass::class);

        $result = $type->parse(['key' => ['foo' => 'bar']], []);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals((object) ['foo' => 'bar'], $result['key']);
    }

    public function testSerializeArrayWithoutInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new AssocArrayType($marshaller);

        $result = $type->serialize(['a' => 1, 'b' => 2]);

        $this->assertEquals((object) ['a' => 1, 'b' => 2], $result);
    }

    public function testSerializeWithInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('marshal')->willReturnCallback(
            fn(object $obj) => (array) $obj,
        );
        $type = new AssocArrayType($marshaller, \stdClass::class);

        $result = $type->serialize(['key' => (object) ['foo' => 'bar']]);

        $this->assertEquals((object) ['key' => ['foo' => 'bar']], $result);
    }

    public function testSerializeIterableWithoutInnerType(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new AssocArrayType($marshaller);

        $generator = (static function () {
            yield 'a' => 1;
            yield 'b' => 2;
        })();

        $result = $type->serialize($generator);

        $this->assertEquals((object) ['a' => 1, 'b' => 2], $result);
    }
}
