<?php

declare(strict_types=1);

namespace Temporal\Testing;

class DeprecationMessage
{
    public function __construct(
        public readonly string $message,
        public readonly string $file,
        public readonly int $line,
        public readonly array $trace,
    ) {}
}
