<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Schedule\Backfill;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\BackfillPeriod;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
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

class BackfillTest extends TestCase
{
    #[Test]
    public static function check(
        ScheduleClientInterface $client,
        Feature $feature,
        State $runtime,
    ): void {
        $workflowId = Uuid::uuid4()->toString();
        $scheduleId = Uuid::uuid4()->toString();

        $handle = $client->createSchedule(
            schedule: Schedule::new()
                ->withAction(
                    StartWorkflowAction::new('Workflow')
                        ->withWorkflowId($workflowId)
                        ->withTaskQueue($feature->taskQueue)
                        ->withInput(['arg1'])
                )->withSpec(
                    ScheduleSpec::new()
                        ->withIntervalList(CarbonInterval::minute(1))
                )->withState(
                    ScheduleState::new()
                        ->withPaused(true)
                ),
            options: ScheduleOptions::new()
                // todo: should namespace be inherited from Service Client options by default?
                ->withNamespace($runtime->namespace),
            scheduleId: $scheduleId,
        );

        try {
            // Run backfill
            $now = CarbonImmutable::now()->setSeconds(0);
            $threeYearsAgo = $now->modify('-3 years');
            $thirtyMinutesAgo = $now->modify('-30 minutes');
            $handle->backfill([
                BackfillPeriod::new(
                    $threeYearsAgo->modify('-2 minutes'),
                    $threeYearsAgo,
                    ScheduleOverlapPolicy::AllowAll,
                ),
                BackfillPeriod::new(
                    $thirtyMinutesAgo->modify('-2 minutes'),
                    $thirtyMinutesAgo,
                    ScheduleOverlapPolicy::AllowAll,
                ),
            ]);

            // Confirm 6 executions
            self::assertSame(6, $handle->describe()->info->numActions);
        } finally {
            $handle->delete();
        }
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    #[WorkflowMethod('Workflow')]
    public function run(string $arg)
    {
        return $arg;
    }
}
