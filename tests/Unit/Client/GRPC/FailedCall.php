<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use Temporal\Client\GRPC\StatusCode;

final class FailedCall
{
    public function __construct(
        private readonly string $details = '',
    ) {}

    public function wait(): array
    {
        return [null, (object) ['code' => StatusCode::UNKNOWN, 'details' => $this->details, 'metadata' => []]];
    }
}
