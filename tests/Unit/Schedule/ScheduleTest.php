<?php

declare(strict_types=1);

namespace Schedule;

use PHPUnit\Framework\TestCase;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Policy\SchedulePolicies;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\ScheduleState;

/**
 * @covers \Temporal\Client\Schedule\Schedule
 */
class ScheduleTest extends TestCase
{
    public function testWithAction(): void
    {
        $init = Schedule::new();

        $new = $init->withAction($a = StartWorkflowAction::new('test-workflow'));

        $this->assertNotSame($init, $new);
        $this->assertSame($a, $new->action);
    }

    public function testWithSpec(): void
    {
        $init = Schedule::new();

        $new = $init->withSpec($s = ScheduleSpec::new());

        $this->assertNotSame($init, $new);
        $this->assertSame($s, $new->spec);
    }

    public function testWithPolicies(): void
    {
        $init = Schedule::new();

        $new = $init->withPolicies($p = SchedulePolicies::new());

        $this->assertNotSame($init, $new);
        $this->assertSame($p, $new->policies);
    }

    public function testWithState(): void
    {
        $init = Schedule::new();

        $new = $init->withState($s = ScheduleState::new());

        $this->assertNotSame($init, $new);
        $this->assertSame($s, $new->state);
    }
}
