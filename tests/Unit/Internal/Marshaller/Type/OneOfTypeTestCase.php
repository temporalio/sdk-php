<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\OneOfType;

#[CoversClass(OneOfType::class)]
final class OneOfTypeTestCase extends TestCase
{
    public function testParseObjectInstanceOfParentClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, \stdClass::class, ['field' => \stdClass::class]);

        $obj = (object) ['foo' => 'bar'];
        $result = $type->parse($obj, null);

        $this->assertSame($obj, $result);
    }

    public function testParseNonArrayThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, \stdClass::class, ['field' => \stdClass::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a type of array');

        $type->parse('string', null);
    }

    public function testParseNullableWithNoCaseDetected(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, \stdClass::class, ['field' => \stdClass::class], nullable: true);

        $result = $type->parse(['unknown' => 'data'], null);

        $this->assertNull($result);
    }

    public function testParseNonNullableWithNoCaseDetectedThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, \stdClass::class, ['field' => \stdClass::class], nullable: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to detect OneOf case');

        $type->parse(['unknown' => 'data'], null);
    }

    public function testParseNonNullableWithNoCaseShowsParentClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, \stdClass::class, ['field' => \stdClass::class], nullable: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('`stdClass`');

        $type->parse(['unknown' => 'data'], null);
    }

    public function testParseNonNullableWithoutParentClass(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, null, ['field' => \stdClass::class], nullable: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to detect OneOf case for non-nullable type.');

        $type->parse(['unknown' => 'data'], null);
    }

    public function testParseDetectedCaseUsesMarshaller(): void
    {
        $target = new class {
            public string $name = '';
        };
        $targetClass = $target::class;

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturnCallback(function (array $data, object $obj) {
                $obj->name = $data['name'];
                return $obj;
            });

        $type = new OneOfType($marshaller, null, ['typeA' => $targetClass]);

        $result = $type->parse(['typeA' => ['name' => 'test']], null);

        $this->assertInstanceOf($targetClass, $result);
        $this->assertSame('test', $result->name);
    }

    public function testParseDetectedCaseWithExistingCurrentOfSameClass(): void
    {
        $target = new class {
            public string $name = '';
        };
        $targetClass = $target::class;

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('unmarshal')
            ->willReturnCallback(function (array $data, object $obj) {
                $obj->name = $data['name'];
                return $obj;
            });

        $type = new OneOfType($marshaller, null, ['typeA' => $targetClass]);

        $existing = clone $target;
        $result = $type->parse(['typeA' => ['name' => 'updated']], $existing);

        $this->assertSame($existing, $result);
        $this->assertSame('updated', $result->name);
    }

    public function testParseStdClassCase(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, null, ['typeA' => \stdClass::class]);

        $current = new \stdClass();
        $result = $type->parse(['typeA' => ['foo' => 'bar']], $current);

        $this->assertSame($current, $result);
        $this->assertSame('bar', $result->foo);
    }

    public function testSerializeNullableNull(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, null, ['typeA' => \stdClass::class], nullable: true);

        $result = $type->serialize(null);

        $this->assertSame([], $result);
    }

    public function testSerializeNonObjectThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, null, ['typeA' => \stdClass::class], nullable: false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a type of object');

        $type->serialize('string');
    }

    public function testSerializeMatchingCase(): void
    {
        $target = new class {
            public string $foo = 'bar';
        };
        $targetClass = $target::class;

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->expects($this->once())
            ->method('marshal')
            ->with($target)
            ->willReturn(['foo' => 'bar']);

        $type = new OneOfType($marshaller, null, ['typeA' => $targetClass]);

        $result = $type->serialize($target);

        $this->assertSame(['typeA' => ['foo' => 'bar']], $result);
    }

    public function testSerializeNoMatchingCaseThrowsTypeError(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new OneOfType($marshaller, null, ['typeA' => \stdClass::class]);

        $unregistered = new class {};

        // serialize method doesn't return when no case matches, causing TypeError
        $this->expectException(\TypeError::class);

        $type->serialize($unregistered);
    }
}
