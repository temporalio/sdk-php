<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\ArrayType;

use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Tests\Unit\DTO\AbstractDTOMarshalling;

class ArrayTestCase extends AbstractDTOMarshalling
{
    public function testMarshalling(): void
    {
        $dto = new ArrayDto();
        $dto->foo = ['foo'];
        $dto->bar = ['bar'];
        $dto->baz = null;
        $dto->autoArray = ['foo'];
        $dto->nullableFoo = ['bar'];
        $dto->nullableBar = null;
        $dto->iterable = (static function (): \Generator {
            yield 'foo';
        })();
        $dto->iterableNullable = null;
        $dto->assoc = ['foo' => 'bar'];
        $dto->assocOfType = ['foo' => (object)['baz' => 'bar']];

        $result = $this->marshal($dto);
        $this->assertSame([
            'foo' => ['foo'],
            'bar' => ['bar'],
            'baz' => null,
            'autoArray' => ['foo'],
            'nullableFoo' => ['bar'],
            'nullableBar' => null,
            'iterable' => ['foo'],
            'iterableNullable' => null,
            'assoc' => ['foo' => 'bar'],
            'assocOfType' => ['foo' => ['baz' => 'bar']],
        ], $result);
    }

    public function testUnmarshalling(): void
    {
        $dto = $this->unmarshal([
            'foo' => ['foo'],
            'bar' => ['bar'],
            'baz' => null,
            'autoArray' => ['foo'],
            'nullableFoo' => ['bar'],
            'nullableBar' => null,
            'iterable' => ['it'],
            'iterableNullable' => ['itn'],
            'assoc' => ['foo' => 'bar'],
            'assocOfType' => ['key' => ['foo' => 'bar']],
        ], new ArrayDto());

        $this->assertSame(['foo'], $dto->foo);
        $this->assertSame(['bar'], $dto->bar);
        $this->assertSame(null, $dto->baz);
        $this->assertSame(['foo'], $dto->autoArray);
        $this->assertSame(['bar'], $dto->nullableFoo);
        $this->assertSame(['it'], $dto->iterable);
        $this->assertSame(['itn'], $dto->iterableNullable);
        $this->assertSame(null, $dto->nullableBar);
        $this->assertSame(['foo' => 'bar'], $dto->assoc);
        $this->assertEquals(['key' => (object)['foo' => 'bar']], $dto->assocOfType);
    }

    public function testSetNullToNotNullable(): void
    {
        $dto = $this->unmarshal([
            'foo' => null,
        ], new ArrayDto());

        self::assertSame([], $dto->foo);
    }

    protected function getTypeMatchers(): array
    {
        return [
            ArrayType::class,
        ];
    }
}
