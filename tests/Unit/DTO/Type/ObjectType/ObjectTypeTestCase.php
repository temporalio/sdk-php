<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\ObjectType;

use ReflectionClass;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\ChildDto;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\ParentDto;
use Temporal\Tests\Unit\DTO\DTOMarshallingTestCase;
use Temporal\Tests\Unit\DTO\Type\ObjectType\Stub\ReadonlyProperty;

final class ObjectTypeTestCase extends DTOMarshallingTestCase
{
    public function testReflectionTypeMarshal(): void
    {
        $dto = new ParentDto(
            new ChildDto('foo')
        );

        $result = $this->marshal($dto);

        $this->assertEquals(['child' => ['foo' => 'foo']], $result);
    }

    public function testReflectionTypeUnmarshal(): void
    {
        $dto = $this->unmarshal([
            'child' => ['foo' => 'bar'],
        ], (new ReflectionClass(ParentDto::class))->newInstanceWithoutConstructor());

        self::assertEquals(new ParentDto(
            new ChildDto('bar')
        ), $dto);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testReadonlyMarshal(): void
    {
        $dto = new ReadonlyProperty(
            new ChildDto('foo')
        );

        $result = $this->marshal($dto);

        $this->assertEquals(['child' => ['foo' => 'foo']], $result);
    }

    /**
     * @requires PHP >= 8.1
     */
    public function testReadonlyUnmarshal(): void
    {
        $dto = $this->unmarshal([
            'child' => ['foo' => 'bar'],
        ], (new ReflectionClass(ReadonlyProperty::class))->newInstanceWithoutConstructor());

        self::assertEquals(new ReadonlyProperty(
            new ChildDto('bar')
        ), $dto);
    }

    protected function getTypeMatchers(): array
    {
        return [
            ObjectType::class,
        ];
    }
}
