<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller;

use PHPUnit\Framework\TestCase;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Tests\Unit\Internal\Marshaller\Fixture\A;
use Temporal\Tests\Unit\Internal\Marshaller\Fixture\B;

/**
 * @internal
 *
 * @covers \Temporal\Internal\Marshaller\Marshaller
 */
final class MarshallerTestCase extends TestCase
{
    public function testNestedNullableObjectWasSerialized(): void
    {
        $marshaller = new Marshaller(new AttributeMapperFactory(new AttributeReader()));
        self::assertEquals(['x' => 'x', 'b' => null], $marshaller->marshal(new A('x')));
    }

    public function testNestedNotNullableObjectWasSerialized(): void
    {
        $marshaller = new Marshaller(new AttributeMapperFactory(new AttributeReader()));
        self::assertEquals(['x' => 'x', 'b' => ['code' => 'y', 'description' => null]], $marshaller->marshal(new A('x', new B('y'))));
    }
}
