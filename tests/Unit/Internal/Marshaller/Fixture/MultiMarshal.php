<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Fixture;

use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalArray;

final class MultiMarshal
{
    #[Marshal(name: 'foo-a')]
    #[Marshal(name: 'foo-b')]
    #[MarshalArray(name: 'foo-c')]
    public string $foo;
}
