<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Input;

final class Feature
{
    public function __construct(
        public string $testClass,
        public string $testNamespace,
        public string $taskQueue,
    ) {}
}
