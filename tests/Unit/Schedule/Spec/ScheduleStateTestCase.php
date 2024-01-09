<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Schedule\Spec;

use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Spec\ScheduleState;

/**
 * @covers \Temporal\Client\Schedule\Spec\ScheduleState
 */
class ScheduleStateTestCase extends TestCase
{
    public function testWithNotes(): void
    {
        $init = ScheduleState::new();

        $new = $init->withNotes('test');

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame('', $init->notes, 'default value was not changed');
        $this->assertSame('test', $new->notes);
    }

    public function testWithPaused(): void
    {
        $init = ScheduleState::new();

        $new = $init->withPaused(true);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertFalse($init->paused, 'default value was not changed');
        $this->assertTrue($new->paused);
    }

    public function testWithLimitedActions(): void
    {
        $init = ScheduleState::new();

        $new = $init->withLimitedActions(true);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertFalse($init->limitedActions, 'default value was not changed');
        $this->assertTrue($new->limitedActions);
    }

    public function testWithRemainingActions(): void
    {
        $init = ScheduleState::new();

        $new = $init->withRemainingActions(5);

        $this->assertNotSame($init, $new, 'immutable method clones object');
        $this->assertSame(0, $init->remainingActions, 'default value was not changed');
        $this->assertSame(5, $new->remainingActions);
    }
}
