<?php

namespace Temporal\Tests\Unit\Internal\Support;

use PHPUnit\Framework\TestCase;
use Temporal\Internal\Support\DateInterval;

class DateIntervalTestCase extends TestCase
{
    public function testFloatyDateIntervalToDuration(): void
    {
        $interval = DateInterval::toDuration(DateInterval::parse(5_000_356_000, DateInterval::FORMAT_NANOSECONDS));

        $this->assertEquals(356_000, $interval->getNanos());
        $this->assertEquals(5, $interval->getSeconds());
    }
}
