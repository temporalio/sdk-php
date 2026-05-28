<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Type\Stub;

enum IntBackedEnum: int
{
    case One = 1;
    case Two = 2;
}
