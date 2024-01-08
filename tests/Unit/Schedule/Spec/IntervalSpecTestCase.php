<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule\Spec;

use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Spec\IntervalSpec;

/**
 * @covers \Temporal\Client\Schedule\Spec\IntervalSpec
 */
class IntervalSpecTestCase extends TestCase
{
    public function testWithIntervalInt(): void
    {
        $init = IntervalSpec::new(10, 0);

        $new = $init->withInterval(5);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('0/0/0/10', $init->interval->format('%y/%h/%i/%s'), 'default value was not changed');
        $this->assertSame('0/0/0/5', $new->interval->format('%y/%h/%i/%s'));
    }

    public function testWithIntervalString(): void
    {
        $init = IntervalSpec::new('P1Y', 0);

        $new = $init->withInterval('P2Y');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('1/0/0/0', $init->interval->format('%y/%h/%i/%s'), 'default value was not changed');
        $this->assertSame('2/0/0/0', $new->interval->format('%y/%h/%i/%s'));
    }

    public function testWithIntervalDateInterval(): void
    {
        $init = IntervalSpec::new(new \DateInterval('P5Y'));

        $new = $init->withInterval(new \DateInterval('P2Y'));

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('5/0/0/0', $init->interval->format('%y/%h/%i/%s'), 'default value was not changed');
        $this->assertSame('2/0/0/0', $new->interval->format('%y/%h/%i/%s'));
        $this->assertSame('0/0/0/0', $init->phase->format('%y/%h/%i/%s'), 'default value was not changed');
    }

    public function testWithPhaseInt(): void
    {
        $init = IntervalSpec::new(5, 10);

        $new = $init->withPhase(5);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('0/0/0/10', $init->phase->format('%y/%h/%i/%s'), 'default value was not changed');
        $this->assertSame('0/0/0/5', $new->phase->format('%y/%h/%i/%s'));
    }

    public function testWithPhaseString(): void
    {
        $init = IntervalSpec::new(10, 'PT20S');

        $new = $init->withPhase('P2Y');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('0/0/0/20', $init->phase->format('%y/%h/%i/%s'), 'default value was not changed');
        $this->assertSame('2/0/0/0', $new->phase->format('%y/%h/%i/%s'));
    }

    public function testWithPhaseDateInterval(): void
    {
        $init = IntervalSpec::new(10, new \DateInterval('PT20S'));

        $new = $init->withPhase(new \DateInterval('P2Y'));

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('0/0/0/20', $init->phase->format('%y/%h/%i/%s'), 'default value was not changed');
        $this->assertSame('2/0/0/0', $new->phase->format('%y/%h/%i/%s'));
    }
}
