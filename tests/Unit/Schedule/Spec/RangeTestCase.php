<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule\Spec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Spec\Range;

#[CoversClass(\Temporal\Client\Schedule\Spec\Range::class)]
class RangeTestCase extends TestCase
{
    public function testWithStart(): void
    {
        $init = Range::new(0, 50, 2);

        $new = $init->withStart(5);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(2, $new->step);
        $this->assertSame(0, $init->start, 'init value was not changed');
        $this->assertSame(5, $new->start);
    }

    public function testWithEnd(): void
    {
        $init = Range::new(0, 50, 1);

        $new = $init->withEnd(5);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(50, $init->end, 'init value was not changed');
        $this->assertSame(5, $new->end);
    }

    public function testWithStep(): void
    {
        $init = Range::new(0, 50);

        $new = $init->withStep(5);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(1, $init->step, 'init value was not changed');
        $this->assertSame(5, $new->step);
    }
}
