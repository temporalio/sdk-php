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

class ArrayDTO
{
    #[MarshalArray(name: 'foo')]
    public array $foo;

    #[MarshalArray(name: 'bar')]
    public ?array $bar;

    #[MarshalArray(name: 'baz')]
    public ?array $baz;

    public array $autoArray;

    public ?array $nullableFoo;

    public ?array $nullableBar;

    public iterable $iterable;

    public ?iterable $iterableNullable;
}
