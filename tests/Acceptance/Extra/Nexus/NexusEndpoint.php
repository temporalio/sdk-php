<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus;

final readonly class NexusEndpoint
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
