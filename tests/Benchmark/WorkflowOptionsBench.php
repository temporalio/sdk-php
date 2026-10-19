<?php

declare(strict_types=1);

namespace Temporal\Tests\Benchmark;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Temporal\Client\WorkflowOptions;

#[Revs(10000)]
#[Iterations(10)]
#[Warmup(1)]
final class WorkflowOptionsBench
{
    public function benchNew(): void
    {
        WorkflowOptions::new();
    }
}
