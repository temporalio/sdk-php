<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\Type\EncodedCollectionType;

#[CoversClass(EncodedCollectionType::class)]
final class EncodedCollectionTypeTestCase extends TestCase
{
    public function testMatchReturnsTrueForEncodedCollection(): void
    {
        $type = $this->createReflectionNamedType(EncodedCollection::class, false);

        $this->assertTrue(EncodedCollectionType::match($type));
    }

    public function testMatchReturnsFalseForBuiltinType(): void
    {
        $type = $this->createReflectionNamedType('array', true);

        $this->assertFalse(EncodedCollectionType::match($type));
    }

    public function testMatchReturnsFalseForUnrelatedClass(): void
    {
        $type = $this->createReflectionNamedType(\stdClass::class, false);

        $this->assertFalse(EncodedCollectionType::match($type));
    }

    public function testParseNull(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller);

        $result = $type->parse(null, null);

        $this->assertInstanceOf(EncodedCollection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function testParseArray(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller);

        $result = $type->parse(['key' => 'value'], null);

        $this->assertInstanceOf(EncodedCollection::class, $result);
        $this->assertSame('value', $result->getValue('key'));
    }

    public function testParseEncodedCollection(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller);

        $collection = EncodedCollection::fromValues(['k' => 'v']);
        $result = $type->parse($collection, null);

        $this->assertSame($collection, $result);
    }

    public function testParseInvalidTypeThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type');

        $type->parse(42, null);
    }

    public function testSerializeWithoutMarshalTo(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller);

        $collection = EncodedCollection::fromValues(['a' => 1, 'b' => 2]);
        $result = $type->serialize($collection);

        $this->assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testSerializeNonEncodedCollectionThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported value type');

        $type->serialize('not a collection');
    }

    public function testSerializeWithUnsupportedMarshalToThrows(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller, \stdClass::class);

        $collection = EncodedCollection::empty();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported target type');

        $type->serialize($collection);
    }

    public function testSerializeWithSearchAttributesMarshalTo(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller, SearchAttributes::class);

        $converter = $this->createMock(DataConverterInterface::class);
        $converter->method('toPayload')->willReturn(new \Temporal\Api\Common\V1\Payload());

        $collection = EncodedCollection::fromValues(['key' => 'val'], $converter);
        $result = $type->serialize($collection);

        $this->assertInstanceOf(SearchAttributes::class, $result);
    }

    public function testSerializeWithMemoMarshalTo(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller, Memo::class);

        $converter = $this->createMock(DataConverterInterface::class);
        $converter->method('toPayload')->willReturn(new \Temporal\Api\Common\V1\Payload());

        $collection = EncodedCollection::fromValues(['key' => 'val'], $converter);
        $result = $type->serialize($collection);

        $this->assertInstanceOf(Memo::class, $result);
    }

    public function testSerializeWithPayloadsMarshalTo(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller, Payloads::class);

        $converter = $this->createMock(DataConverterInterface::class);
        $converter->method('toPayload')->willReturn(new \Temporal\Api\Common\V1\Payload());

        $collection = EncodedCollection::fromValues(['key' => 'val'], $converter);
        $result = $type->serialize($collection);

        $this->assertInstanceOf(Payloads::class, $result);
    }

    public function testSerializeWithHeaderMarshalTo(): void
    {
        $marshaller = $this->createMock(MarshallerInterface::class);
        $type = new EncodedCollectionType($marshaller, Header::class);

        $converter = $this->createMock(DataConverterInterface::class);
        $converter->method('toPayload')->willReturn(new \Temporal\Api\Common\V1\Payload());

        $collection = EncodedCollection::fromValues(['key' => 'val'], $converter);
        $result = $type->serialize($collection);

        $this->assertInstanceOf(Header::class, $result);
    }

    public function testMakeRuleReturnsNullForBuiltinType(): void
    {
        $property = $this->createPropertyWithType('array', true, false);

        $this->assertNull(EncodedCollectionType::makeRule($property));
    }

    public function testMakeRuleReturnsNullForNonMatchingClass(): void
    {
        $property = $this->createPropertyWithType(\stdClass::class, false, false);

        $this->assertNull(EncodedCollectionType::makeRule($property));
    }

    public function testMakeRuleReturnsRuleForEncodedCollection(): void
    {
        $property = $this->createPropertyWithType(EncodedCollection::class, false, false);

        $rule = EncodedCollectionType::makeRule($property);

        $this->assertNotNull($rule);
        $this->assertSame(EncodedCollectionType::class, $rule->type);
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
