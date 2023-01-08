<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\DeepDTO;

final class ChildDTO
{
    public function __construct(
        public readonly string $foo,
    ) {
    }
}
