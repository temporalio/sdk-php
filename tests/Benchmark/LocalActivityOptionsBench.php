<?php

declare(strict_types=1);

namespace Temporal\Tests\Benchmark;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Temporal\Activity\LocalActivityOptions;

#[Revs(10000)]
#[Iterations(10)]
#[Warmup(1)]
final class LocalActivityOptionsBench
{
    public function benchNew(): void
    {
        LocalActivityOptions::new();
    }
}
