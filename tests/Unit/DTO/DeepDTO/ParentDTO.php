<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\DTO\DeepDTO;

final class ParentDTO
{
    public function __construct(
        public readonly ChildDTO $child,
    ) {
    }
}
