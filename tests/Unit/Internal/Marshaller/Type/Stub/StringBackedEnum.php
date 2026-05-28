<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type\Stub;

enum StringBackedEnum: string
{
    case Foo = 'foo';
    case Bar = 'bar';
}
