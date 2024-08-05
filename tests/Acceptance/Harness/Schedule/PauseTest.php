<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Schedule\Pause;

use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\ScheduleOptions;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\ScheduleState;
use Temporal\Client\ScheduleClientInterface;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class PauseTest extends TestCase
{
    #[Test]
    public static function check(
        ScheduleClientInterface $client,
        Feature $feature,
        State $runtime,
    ): void {
        $handle = $client->createSchedule(
            schedule: Schedule::new()
                ->withAction(
                    StartWorkflowAction::new('HarnessWorkflow_Schedule_Pause')
                        ->withTaskQueue($feature->taskQueue)
                        ->withInput(['arg1'])
                )->withSpec(
                    ScheduleSpec::new()
                        ->withIntervalList(CarbonInterval::minute(1))
                )->withState(
                    ScheduleState::new()
                        ->withPaused(true)
                        ->withNotes('initial note')
                ),
            options: ScheduleOptions::new()
                ->withNamespace($runtime->namespace),
        );

        try {
            // Confirm pause
            $state = $handle->describe()->schedule->state;
            self::assertTrue($state->paused);
            self::assertSame('initial note', $state->notes);
            // Re-pause
            $handle->pause('custom note1');
            $state = $handle->describe()->schedule->state;
            self::assertTrue($state->paused);
            self::assertSame('custom note1', $state->notes);
            // Unpause
            $handle->unpause();
            $state = $handle->describe()->schedule->state;
            self::assertFalse($state->paused);
            self::assertSame('Unpaused via PHP SDK', $state->notes);
            // Pause
            $handle->pause();
            $state = $handle->describe()->schedule->state;
            self::assertTrue($state->paused);
            self::assertSame('Paused via PHP SDK', $state->notes);
        } finally {
            $handle->delete();
        }
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('HarnessWorkflow_Schedule_Pause')]
    public function run(string $arg)
    {
        return $arg;
    }
}
