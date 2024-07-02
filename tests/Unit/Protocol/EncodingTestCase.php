<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\Workflow\ReturnType;

/**
 * @group unit
 * @group protocol
 */
class EncodingTestCase extends AbstractProtocol
{
    #[Test]
    public function nullValuesAreReturned(): void
    {
        $encodedValues = EncodedValues::fromValues([null, 'something'], new DataConverter());
        $this->assertNull($encodedValues->getValue(0));
    }

    public static function getNotNullableTypes(): iterable
    {
        yield [Type::create(Type::TYPE_ARRAY)];
        yield [Type::create(Type::TYPE_OBJECT)];
        yield [Type::create(Type::TYPE_STRING)];
        yield [Type::create(Type::TYPE_BOOL)];
        yield [Type::create(Type::TYPE_INT)];
        yield [Type::create(Type::TYPE_FLOAT)];
        yield [Type::create(Type::TYPE_TRUE)];
        yield [Type::create(Type::TYPE_FALSE)];
        yield [Type::create(self::class)];
        yield [Type::TYPE_ARRAY];
        yield [Type::TYPE_OBJECT];
        yield [Type::TYPE_STRING];
        yield [Type::TYPE_BOOL];
        yield [Type::TYPE_INT];
        yield [Type::TYPE_FLOAT];
        yield [Type::TYPE_TRUE];
        yield [Type::TYPE_FALSE];
        yield [self::class];
        yield [new ReturnType(self::class)];
        yield [self::getReturnType(static fn(): string => '')];
        yield [self::getReturnType(static fn(): int => 0)];
        yield [self::getReturnType(static fn(): float => 0.0)];
        yield [self::getReturnType(static fn(): bool => false)];
        yield [self::getReturnType(static fn(): array => [])];
        yield [self::getReturnType(static fn(): object => new \stdClass())];
        yield 'union' => [[self::getReturnType(static fn(): int|string => 0)]];
    }

    public static function getNullableTypes(): iterable
    {
        yield [null];
        yield [Type::create(Type::TYPE_ANY)];
        yield [Type::create(Type::TYPE_VOID)];
        yield [Type::create(Type::TYPE_NULL)];
        yield [new Type(self::class, true)];
        yield [new ReturnType(self::class, true)];
        yield [Type::TYPE_ANY];
        yield [Type::TYPE_VOID];
        yield [Type::TYPE_NULL];
        yield 'nullable' => [self::getReturnType(static fn(): ?string => null)];
        yield 'mixed' => [self::getReturnType(static fn(): mixed => null)];
        yield 'void' => [self::getReturnType(static function (): void {})];
        yield 'union' => [self::getReturnType(static fn(): int|string|null => null)];
    }

    #[Test]
    #[DataProvider('getNullableTypes')]
    public function payloadWithoutValueDecoding(mixed $type): void
    {
        $encodedValues = EncodedValues::fromPayloadCollection(new \ArrayIterator([]));

        self::assertNull($encodedValues->getValue(0, $type));
    }

    #[Test]
    #[DataProvider('getNotNullableTypes')]
    public function payloadWithoutValueDecodingNotNullable(mixed $type): void
    {
        $encodedValues = EncodedValues::fromPayloadCollection(new \ArrayIterator([]));

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('DataConverter is not set');

        $encodedValues->getValue(0, $type);
    }

    private static function getReturnType(\Closure $closure): \ReflectionType
    {
        return (new \ReflectionFunction($closure))->getReturnType();
    }
}
