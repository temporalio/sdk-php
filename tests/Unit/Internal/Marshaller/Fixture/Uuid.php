<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Marshaller\Fixture;

use Ramsey\Uuid\UuidInterface;

final class Uuid
{
    public function __construct(
        public UuidInterface $uuid,
        public ?UuidInterface $nullableUuid = null
    ) {
    }
}
