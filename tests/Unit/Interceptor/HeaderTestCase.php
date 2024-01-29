<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Interceptor;

use PHPUnit\Framework\Attributes\DataProvider;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\Header;
use Temporal\Tests\Unit\AbstractUnit;

/**
 * @group unit
 * @group interceptor
 */
class HeaderTestCase extends AbstractUnit
{
    public function testToHeaderFromValuesWithoutConverterException(): void
    {
        $header = Header::empty()->withValue('foo', 'bar');
        \assert($header instanceof Header);

        $this->assertCount(1, $header);
        $this->assertSame('bar', $header->getValue('foo'));

        $this->expectException(\LogicException::class);
        $header->toHeader();
    }

    public function testToHeaderFromValuesWithConverter(): void
    {
        $converter = $this->getDataConverter();
        $header = Header::empty()->withValue('foo', 'bar');
        \assert($header instanceof Header);
        $header->setDataConverter($converter);

        $this->assertCount(1, $header);
        $this->assertSame('bar', $header->getValue('foo'));

        $header->toHeader();
        $collection = $header->toHeader()->getFields();
        $this->assertCount(1, $collection);
        $this->assertSame('bar', $converter->fromPayload($collection->offsetGet('foo'), null));
    }

    public function testWithValueImmutability(): void
    {
        $source = Header::empty();

        $collection = $source->withValue('foo', 'bar');

        $this->assertCount(1, $collection);
        $this->assertSame('bar', $collection->getValue('foo'));
        // Immutability
        $this->assertNotSame($collection, $source);
    }

    #[DataProvider('fromValuesProvider')]
    public function testFromValues(array $input, array $output): void
    {
        $collection = Header::fromValues($input);

        $this->assertSame($output, $collection->getValues());
    }

    public function testOverwriteProtoWithValue(): void
    {
        $header = Header::fromValues(['foo' => 'bar']);
        \assert($header instanceof Header);
        $header->setDataConverter($this->getDataConverter());
        $protoCollection = $header->toHeader()->getFields();

        $header = Header::fromPayloadCollection($protoCollection, $this->getDataConverter());

        // Check
        $this->assertSame('bar', $header->getValue('foo'));

        // Overwrite `foo` value
        $this->assertCount(1, $header);
        $header = $header->withValue('foo', 'baz');

        $this->assertCount(1, $header);
        $this->assertSame('baz', $header->getValue('foo'));
    }

    public function testProtoWithValue(): void
    {
        $header = Header::fromValues(['foo' => 'bar']);
        \assert($header instanceof Header);
        $header->setDataConverter($this->getDataConverter());
        $protoCollection = $header->toHeader()->getFields();

        $header = Header::fromPayloadCollection($protoCollection, $this->getDataConverter())
            ->withValue('baz', 'qux');

        // Overwrite `foo` value
        $this->assertCount(2, $header);
        $this->assertSame('bar', $header->getValue('foo'));
        $this->assertSame('qux', $header->getValue('baz'));
    }

    public function testEmptyHeaderToProtoPackable(): void
    {
        $collection = Header::empty();
        \assert($collection instanceof Header);

        $header = $collection->toHeader();
        $header->serializeToString();
        // There is no exception
        $this->assertTrue(true);
    }

    public function testHeaderFromValuesToProtoPackable(): void
    {
        $converter = $this->getDataConverter();
        $header = Header::fromValues(['foo' => 'bar']);
        \assert($header instanceof Header);
        $header->setDataConverter($converter);

        $collection = $header->toHeader()->getFields();
        $this->assertCount(1, $collection);
        $this->assertSame('bar', $converter->fromPayload($collection->offsetGet('foo'), null));
    }

    public static function fromValuesProvider(): iterable
    {
        yield [
            ['foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo'],
            ['foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo'],
        ];

        yield [
            [1 => 'bar', 2 => 4, 3 => 0.5],
            [1 => 'bar', 2 => 4, 3 => 0.5],
        ];

        yield [
            ['foo' => null, 'bar' => $x = new class implements \Stringable {
                public function __toString(): string
                {
                    return 'baz';
                }
            }, 'baz' => false],
            ['foo' => null, 'bar' => $x, 'baz' => false],
        ];
    }

    private function getDataConverter(): DataConverterInterface
    {
        return DataConverter::createDefault();
    }
}
