<?php

declare(strict_types=1);

namespace Temporal\Tests\Benchmark;

use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Temporal\Activity\ActivityOptions;

#[Revs(10000)]
#[Iterations(10)]
#[Warmup(1)]
final class ActivityOptionsBench
{
    public function benchNew(): void
    {
        ActivityOptions::new();
    }
}
