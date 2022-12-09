<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DataConverter;

use Temporal\DataConverter\EncodedHeader;
use Temporal\Tests\Unit\UnitTestCase;

/**
 * @group unit
 * @group data-converter
 */
class EncodedHeaderTestCase extends UnitTestCase
{
    public function testEmptyWithValue(): void
    {
        $source = EncodedHeader::empty();

        $collection = $source->withValue('foo', 'bar');

        $this->assertCount(1, $collection);
        $this->assertSame('bar', $collection->getValue('foo'));
        // Immutability
        $this->assertNotSame($collection, $source);
        $this->assertNotSame($collection->toHeader()->getFields(), $source->toHeader()->getFields());
    }

    public function testEmptyHeaderToProtoPackable(): void
    {
        $collection = EncodedHeader::empty();

        $header = $collection->toHeader();
        $header->serializeToString();
        // There is no exception
        $this->assertTrue(true);
    }

    public function testHeaderFromValuesToProtoPackable(): void
    {
        $collection = EncodedHeader::fromValues(['foo' => 'bar']);

        $header = $collection->toHeader();
        $header->serializeToString();
        // There is no exception
        $this->assertTrue(true);
    }
}
