<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\ArrayType;

use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;

class ArrayTestCase extends DTOMarshallingTestCase
{
    public function testMarshalling(): void
    {
        $dto = new ArrayDTO();
        $dto->foo = ['foo'];
        $dto->bar = ['bar'];
        $dto->baz = null;
        $dto->autoArray = ['foo'];
        $dto->nullableFoo = ['bar'];
        $dto->nullableBar = null;

        $result = $this->marshal($dto);
        $this->assertSame([
            'foo' => ['foo'],
            'bar' => ['bar'],
            'baz' => null,
            'autoArray' => ['foo'],
            'nullableFoo' => ['bar'],
            'nullableBar' => null,
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
        ], new ArrayDTO());

        $this->assertSame(['foo'], $dto->foo);
        $this->assertSame(['bar'], $dto->bar);
        $this->assertSame(null, $dto->baz);
        $this->assertSame(['foo'], $dto->autoArray);
        $this->assertSame(['bar'], $dto->nullableFoo);
        $this->assertSame(null, $dto->nullableBar);
    }

    public function testSetNullToNotNullable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Passed value must be a type of array, but null given');
        $this->unmarshal([
            'foo' => null,
        ], new ArrayDTO());
    }
}
