<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Tests\Unit\Internal\Marshaller\Fixture\A;
use Temporal\Tests\Unit\Internal\Marshaller\Fixture\B;
use Temporal\Tests\Unit\Internal\Marshaller\Fixture\Uuid;

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

    public function testMarshalUuid(): void
    {
        $marshaller = new Marshaller(new AttributeMapperFactory(new AttributeReader()));

        $this->assertSame(
            ['uuid' => 'd1fb065d-f118-477d-a62a-ef93dc7ee03f', 'nullableUuid' => null],
            $marshaller->marshal(new Uuid(UuidV4::fromString('d1fb065d-f118-477d-a62a-ef93dc7ee03f')))
        );

        $this->assertSame(
            [
                'uuid' => 'd1fb065d-f118-477d-a62a-ef93dc7ee03f',
                'nullableUuid' => 'c4cf52f6-32ba-428c-ae7d-25aaa4057f5b'
            ],
            $marshaller->marshal(new Uuid(
                UuidV4::fromString('d1fb065d-f118-477d-a62a-ef93dc7ee03f'),
                UuidV4::fromString('c4cf52f6-32ba-428c-ae7d-25aaa4057f5b'),
            ))
        );
    }

    public function testUnmarshalUuid(): void
    {
        $marshaller = new Marshaller(new AttributeMapperFactory(new AttributeReader()));

        $ref = new \ReflectionClass(Uuid::class);

        $this->assertEquals(
            new Uuid(UuidV4::fromString('d1fb065d-f118-477d-a62a-ef93dc7ee03f'), null),
            $marshaller->unmarshal(
                ['uuid' => 'd1fb065d-f118-477d-a62a-ef93dc7ee03f', 'nullableUuid' => null],
                $ref->newInstanceWithoutConstructor()
            )
        );

        $this->assertEquals(
            new Uuid(
                UuidV4::fromString('d1fb065d-f118-477d-a62a-ef93dc7ee03f'),
                UuidV4::fromString('c4cf52f6-32ba-428c-ae7d-25aaa4057f5b'),
            ),
            $marshaller->unmarshal(
                [
                    'uuid' => 'd1fb065d-f118-477d-a62a-ef93dc7ee03f',
                    'nullableUuid' => 'c4cf52f6-32ba-428c-ae7d-25aaa4057f5b'
                ],
                $ref->newInstanceWithoutConstructor()
            )
        );
    }
}
