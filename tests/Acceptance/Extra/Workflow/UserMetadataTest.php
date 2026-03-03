<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\UserMetadata;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityOptions;
use Temporal\Api\Common\V1\Payload;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\Spec\ScheduleState;
use Temporal\Client\ScheduleClientInterface;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\TimerOptions;
use Temporal\Workflow\UpdateMethod;

class UserMetadataTest extends TestCase
{
    #[Test]
    public function initMetadata(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_UserMetadata',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withStaticSummary('test summary')
                ->withStaticDetails('test details'),
        );

        try {
            /** @see TestWorkflow::handle() */
            $client->start($stub);
            $stub->update('ping');

            $description = $stub->describe();
            self::assertSame('test summary', $description->config->userMetadata->summary);
            self::assertSame('test details', $description->config->userMetadata->details);

            // Complete workflow
            /** @see TestWorkflow::exit */
            $stub->signal('exit');
            $stub->getResult();

            $description = $stub->describe();
            self::assertSame('test summary', $description->config->userMetadata->summary);
            self::assertSame('test details', $description->config->userMetadata->details);
        } finally {
            self::terminate($stub);
        }
    }

    #[Test]
    public function childWorkflowMetadata(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stub = $client->newUntypedWorkflowStub(
            'Extra_Workflow_UserMetadata',
            WorkflowOptions::new()
                ->withTaskQueue($feature->taskQueue)
                ->withStaticSummary('test summary')
                ->withStaticDetails('test details'),
        );

        try {
            /** @see TestWorkflow::handle() */
            $client->start($stub);
            /** @see TestWorkflow::startChild() */
            $childId = (string) $stub->update('start_child', 'child summary', 'child details')->getValue(0);

            $child = $client->newUntypedRunningWorkflowStub($childId);
            $description = $child->describe();
            self::assertSame('child summary', $description->config->userMetadata->summary);
            self::assertSame('child details', $description->config->userMetadata->details);
        } finally {
            self::terminate($stub);
        }
    }

    #[Test]
    public function scheduleWorkflowMetadata(
        ScheduleClientInterface $client,
        Feature $feature,
    ): void {
        $schedule = $client->createSchedule(
            Schedule::new()
                ->withAction(
                    StartWorkflowAction::new('Extra_Workflow_UserMetadata')
                        ->withTaskQueue($feature->taskQueue)
                        ->withStaticSummary('some-summary')
                        ->withStaticDetails('some-details'),
                )
                ->withState(
                    ScheduleState::new()
                        ->withPaused(true),
                ),
        );

        try {
            $description = $schedule->describe();

            $action = $description->schedule->action;
            self::assertInstanceOf(StartWorkflowAction::class, $action);
            self::assertSame('some-summary', $action->userMetadata->summary);
            self::assertSame('some-details', $action->userMetadata->details);
        } finally {
            // Cleanup
            $schedule->delete();
        }
    }

    /**
     * Test that timer metadata is correctly set and can be retrieved.
     */
    #[Test]
    public function timerMetadata(
        #[Stub('Extra_Workflow_UserMetadata')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        DataConverterInterface $dataConverter,
    ): void {
        try {
            /** @see TestWorkflow::exit() */
            $stub->signal('exit');
            $stub->getResult();

            # Check if the timer metadata is set correctly
            $found = false;
            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                if ($event->hasTimerStartedEventAttributes()) {
                    $payload = $event->getUserMetadata()?->getSummary();
                    self::assertInstanceOf(Payload::class, $payload);
                    $data = $dataConverter->fromPayload($payload, 'string');
                    self::assertSame('test timer summary', $data);
                    $found = true;
                    break;
                }
            }

            self::assertTrue($found, 'Timer metadata not found in workflow history');
        } finally {
            self::terminate($stub);
        }
    }

    #[Test]
    public function activityMetadata(
        #[Stub('Extra_Workflow_UserMetadata')]
        WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        DataConverterInterface $dataConverter,
    ): void {
        try {
            /** @see TestWorkflow::executeActivity() */
            $fromActivity = (string) $stub->update('execute_activity', 'test activity summary')->getValue(0);
            self::assertSame('done', $fromActivity);

            # Check that the activity was executed and metadata was set
            $found = false;
            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                if ($event->hasActivityTaskScheduledEventAttributes()) {
                    $payload = $event->getUserMetadata()?->getSummary();
                    self::assertInstanceOf(Payload::class, $payload);
                    $data = $dataConverter->fromPayload($payload, 'string');
                    self::assertSame('test activity summary', $data);
                    $found = true;
                    break;
                }
            }

            self::assertTrue($found, 'Activity metadata not found in workflow history');
        } finally {
            self::terminate($stub);
        }
    }

    private static function terminate(WorkflowStubInterface $stub): void
    {
        try {
            $stub->terminate('');
        } catch (\Throwable) {
            // Do nothing
        }
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $result = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_UserMetadata")]
    public function handle()
    {
        $timer = Workflow::timer(30, TimerOptions::new()->withSummary('test timer summary'));
        yield Workflow::await($timer, fn() => $this->exit);
        return $this->result;
    }

    #[UpdateMethod]
    public function ping(): string
    {
        return 'pong';
    }

    #[UpdateMethod('start_child')]
    public function startChild(string $summary, string $details)
    {
        $stub = Workflow::newUntypedChildWorkflowStub(
            'Extra_Workflow_UserMetadata',
            ChildWorkflowOptions::new()->withStaticSummary($summary)->withStaticDetails($details),
        );
        $execution = yield $stub->start();

        return $execution->getID();
    }

    #[UpdateMethod('execute_activity')]
    public function executeActivity(string $summary)
    {
        /** @see TestActivity::execute() */
        return yield Workflow::executeActivity(
            'Extra_Workflow_UserMetadata.execute',
            options: ActivityOptions::new()
                ->withScheduleToCloseTimeout(30)
                ->withSummary($summary),
        );
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}

#[ActivityInterface('Extra_Workflow_UserMetadata.')]
class TestActivity
{
    public function execute(): string
    {
        return 'done';
    }
}
