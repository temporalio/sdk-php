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
        ], new ArrayDto());

        $this->assertSame(['foo'], $dto->foo);
        $this->assertSame(['bar'], $dto->bar);
        $this->assertSame(null, $dto->baz);
        $this->assertSame(['foo'], $dto->autoArray);
        $this->assertSame(['bar'], $dto->nullableFoo);
        $this->assertSame(['it'], $dto->iterable);
        $this->assertSame(['itn'], $dto->iterableNullable);
        $this->assertSame(null, $dto->nullableBar);
    }

    public function testSetNullToNotNullable(): void
    {
        try {
            $this->unmarshal([
                'foo' => null,
            ], new ArrayDto());

            $this->fail('Null value should not be allowed.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString(
                '`foo`',
                $e->getMessage(),
            );
            $this->assertInstanceOf(\InvalidArgumentException::class, $e->getPrevious());
            $this->assertStringContainsString(
                'Passed value must be a type of array, but null given',
                $e->getPrevious()->getMessage(),
            );
        }
    }

    protected function getTypeMatchers(): array
    {
        return [
            ArrayType::class,
        ];
    }
}
