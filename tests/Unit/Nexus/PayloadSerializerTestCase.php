<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use Temporal\DataConverter\DataConverter;
use Temporal\Nexus\PayloadSerializer;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group nexus
 */
final class PayloadSerializerTestCase extends AbstractUnit
{
    private PayloadSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new PayloadSerializer(DataConverter::createDefault());
    }

    public function testSerializeString(): void
    {
        $content = $this->serializer->serialize('hello');

        self::assertNotEmpty($content->data);
        self::assertNotEmpty($content->headers);
    }

    public function testDeserializeString(): void
    {
        $content = $this->serializer->serialize('hello world');
        $result = $this->serializer->deserialize($content, 'string');

        self::assertSame('hello world', $result);
    }

    public function testRoundTripString(): void
    {
        $value = 'test value';
        $content = $this->serializer->serialize($value);
        $result = $this->serializer->deserialize($content, 'string');

        self::assertSame($value, $result);
    }

    public function testRoundTripInt(): void
    {
        $value = 42;
        $content = $this->serializer->serialize($value);
        $result = $this->serializer->deserialize($content, 'int');

        self::assertSame($value, $result);
    }

    public function testRoundTripFloat(): void
    {
        $value = 3.14;
        $content = $this->serializer->serialize($value);
        $result = $this->serializer->deserialize($content, 'float');

        self::assertSame($value, $result);
    }

    public function testRoundTripBool(): void
    {
        $content = $this->serializer->serialize(true);
        $result = $this->serializer->deserialize($content, 'bool');

        self::assertTrue($result);
    }

    public function testRoundTripNull(): void
    {
        $content = $this->serializer->serialize(null);
        $result = $this->serializer->deserialize($content, 'void');

        self::assertNull($result);
    }

    public function testRoundTripArray(): void
    {
        $value = ['key' => 'value', 'nested' => [1, 2, 3]];
        $content = $this->serializer->serialize($value);
        $result = $this->serializer->deserialize($content, 'array');

        self::assertSame($value, $result);
    }

    public function testSerializePreservesMetadata(): void
    {
        $content = $this->serializer->serialize('test');

        self::assertArrayHasKey('encoding', $content->headers);
    }

    public function testDeserializeVoidReturnsNull(): void
    {
        $content = $this->serializer->serialize('ignored');
        $result = $this->serializer->deserialize($content, 'void');

        self::assertNull($result);
    }
}
