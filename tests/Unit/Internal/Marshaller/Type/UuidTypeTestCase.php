<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallingRule;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\UuidType;
use Temporal\Tests\Unit\Internal\Marshaller\Fixture\PropertyType;

final class UuidTypeTestCase extends TestCase
{
    private Marshaller $marshaller;

    protected function setUp(): void
    {
        $this->marshaller = new Marshaller(new AttributeMapperFactory(new AttributeReader()));
    }

    /**
     * @dataProvider matchDataProvider
     */
    public function testMatch(string $property, bool $expected): void
    {
        $this->assertSame(
            UuidType::match((new \ReflectionProperty(PropertyType::class, $property))->getType()),
            $expected
        );
    }

    /**
     * @dataProvider makeRuleDataProvider
     */
    public function testMakeRule(string $property, mixed $expected): void
    {
        $this->assertEquals(
            UuidType::makeRule(new \ReflectionProperty(PropertyType::class, $property)),
            $expected
        );
    }

    public function testParse(): void
    {
        $type = new UuidType($this->marshaller);

        $this->assertEquals(
            Uuid::fromString('d1fb065d-f118-477d-a62a-ef93dc7ee03f'),
            $type->parse('d1fb065d-f118-477d-a62a-ef93dc7ee03f', null)
        );
    }

    public function testSerialize(): void
    {
        $type = new UuidType($this->marshaller);

        $this->assertEquals(
            'd1fb065d-f118-477d-a62a-ef93dc7ee03f',
            $type->serialize(Uuid::fromString('d1fb065d-f118-477d-a62a-ef93dc7ee03f'))
        );
    }

    public static function matchDataProvider(): \Traversable
    {
        yield ['string', false];
        yield ['int', false];
        yield ['float', false];
        yield ['bool', false];
        yield ['array', false];
        yield ['nullableString', false];
        yield ['nullableInt', false];
        yield ['nullableFloat', false];
        yield ['nullableBool', false];
        yield ['nullableArray', false];
        yield ['uuid', true];
        yield ['nullableUuid', true];
    }

    public static function makeRuleDataProvider(): \Traversable
    {
        yield ['string', null];
        yield ['int', null];
        yield ['float', null];
        yield ['bool', null];
        yield ['array', null];
        yield ['nullableString', null];
        yield ['nullableInt', null];
        yield ['nullableFloat', null];
        yield ['nullableBool', null];
        yield ['nullableArray', null];
        yield [
            'uuid',
            new MarshallingRule('uuid', UuidType::class, UuidInterface::class)
        ];
        yield [
            'nullableUuid',
            new MarshallingRule(
                'nullableUuid',
                NullableType::class,
                new MarshallingRule(type: UuidType::class, of: UuidInterface::class),
            )
        ];
    }
}
