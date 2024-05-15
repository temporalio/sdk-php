<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\Type\ArrayType;

use Temporal\Internal\Marshaller\Meta\MarshalArray;

class ArrayDto
{
    #[MarshalArray(name: 'foo', nullable: false)]
    public array $foo;

    #[MarshalArray(name: 'bar', nullable: true)]
    public ?array $bar;

    #[MarshalArray(name: 'baz', nullable: true)]
    public ?array $baz;

    public array $autoArray;

    public ?array $nullableFoo;

    public ?array $nullableBar;

    public iterable $iterable;

    public ?iterable $iterableNullable;

    public array $assoc;

    #[MarshalArray(of: \stdClass::class)]
    public array $assocOfType;
}
