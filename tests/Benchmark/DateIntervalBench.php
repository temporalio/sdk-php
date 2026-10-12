<?php

declare(strict_types=1);

namespace Temporal\Tests\Benchmark;

use Carbon\CarbonInterval;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Spiral\Attributes\AttributeReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Support\DateInterval;

#[BeforeMethods('setUp')]
#[Revs(10000)]
#[Iterations(10)]
#[Warmup(1)]
final class DateIntervalBench
{
    private CarbonInterval $interval;
    private DateIntervalType $nanoseconds;

    public function setUp(): void
    {
        $this->interval = CarbonInterval::create(0, 0, 0, 0, 1, 30, 15, 250_000);
        $this->nanoseconds = new DateIntervalType(new Marshaller(new AttributeMapperFactory(new AttributeReader())));
    }

    public function benchToDuration(): void
    {
        DateInterval::toDuration($this->interval);
    }

    public function benchSerializeNanoseconds(): void
    {
        $this->nanoseconds->serialize($this->interval);
    }
}
