<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Update\DynamicUpdate;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\ScheduleOptions;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Update\ScheduleUpdate;
use Temporal\Client\Schedule\Update\ScheduleUpdateInput;
use Temporal\Client\ScheduleClientInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Tests\Acceptance\App\TestCase;

class ScheduleUpdateTest extends TestCase
{
    #[Test]
    public function searchAttributesClearViaUpdate(
        ScheduleClientInterface $client,
    ): void
    {
        // Create a new schedule
        $handle = $client->createSchedule(
            Schedule::new()
                ->withAction(
                    StartWorkflowAction::new('TestWorkflow')
                )->withSpec(
                    ScheduleSpec::new()
                        ->withStartTime('+1 hour')
                ),
            ScheduleOptions::new()
                ->withMemo(['memokey2' => 'memoval2'])
                ->withSearchAttributes(
                    EncodedCollection::fromValues([
                        'foo' => 'bar',
                        'bar' => 42,
                    ])
                )
        );

        try {
            $description = $handle->describe();
            self::assertEquals(2, $description->searchAttributes->count());

            // Update the schedule search attribute by clearing them
            $handle->update(function (ScheduleUpdateInput $input): ScheduleUpdate {
                $schedule = $input->description->schedule;
                return ScheduleUpdate::new($schedule)
                    ->withSearchAttributes(EncodedCollection::empty());
            });

            sleep(1);
            self::assertEquals(0, $handle->describe()->searchAttributes->count());
        } finally {
            $handle->delete();
        }
    }

    #[Test]
    public function searchAttributesAddViaUpdate(
        ScheduleClientInterface $client,
    ): void
    {
        // Create a new schedule
        $handle = $client->createSchedule(
            Schedule::new()
                ->withAction(
                    StartWorkflowAction::new('TestWorkflow')
                )->withSpec(
                    ScheduleSpec::new()
                        ->withStartTime('+1 hour')
                ),
            ScheduleOptions::new()
                ->withMemo(['memokey2' => 'memoval2'])
                ->withSearchAttributes(
                    EncodedCollection::fromValues([
                        'foo' => 'bar',
                    ])
                )
        );

        try {
            $description = $handle->describe();
            self::assertEquals(1, $description->searchAttributes->count());

            // Update the schedule search attribute by clearing them
            $handle->update(function (ScheduleUpdateInput $input): ScheduleUpdate {
                $schedule = $input->description->schedule;
                return ScheduleUpdate::new($schedule)
                    ->withSearchAttributes($input->description->searchAttributes->withValue('bar', 69));
            });

            sleep(1);
            self::assertEquals(2, $handle->describe()->searchAttributes->count());
            self::assertSame(69, $handle->describe()->searchAttributes->getValue('bar'));
        } finally {
            $handle->delete();
        }
    }

    #[Test]
    public function update(
        ScheduleClientInterface $client,
    ): void {
        // Create a new schedule
        $handle = $client->createSchedule(
            Schedule::new()
                ->withAction(
                    StartWorkflowAction::new('TestWorkflow')
                        ->withMemo(['memokey1' => 'memoval1'])
                )->withSpec(
                    ScheduleSpec::new()
                        ->withStartTime('+1 hour')
                ),
            ScheduleOptions::new()
                ->withMemo(['memokey2' => 'memoval2'])
                ->withSearchAttributes(EncodedCollection::fromValues([
                    'foo' => 'bar',
                    'bar' => 42,
                ]))
        );

        try {
            // Describe the schedule
            $description = $handle->describe();
            self::assertSame("memoval2", $description->memo->getValue("memokey2"));
            self::assertEquals(2, $description->searchAttributes->count());

            /** @var StartWorkflowAction $startWfAction */
            $startWfAction = $description->schedule->action;
            self::assertSame('memoval1', $startWfAction->memo->getValue("memokey1"));

            // Add memo and update task timeout
            $handle->update(function (ScheduleUpdateInput $input): ScheduleUpdate {
                $schedule = $input->description->schedule;
                /** @var StartWorkflowAction $action */
                $action = $schedule->action;
                $action = $action->withWorkflowTaskTimeout('7 minutes')
                    ->withMemo(['memokey3' => 'memoval3']);
                return ScheduleUpdate::new($schedule->withAction($action));
            });

            $description = $handle->describe();
            self::assertInstanceOf(StartWorkflowAction::class, $description->schedule->action);
            self::assertSame("memoval2", $description->memo->getValue("memokey2"));
            $startWfAction = $description->schedule->action;
            self::assertSame("memoval3", $startWfAction->memo->getValue("memokey3"));
            $this->assertEqualIntervals(new \DateInterval('PT7M'), $startWfAction->workflowTaskTimeout);

            // Update the schedule state
            $expectedUpdateTime = $description->info->lastUpdateAt;
            $handle->update(function (ScheduleUpdateInput $input): ScheduleUpdate {
                $schedule = $input->description->schedule;
                $schedule = $schedule->withState($schedule->state->withPaused(true));
                return ScheduleUpdate::new($schedule);
            });
            $description = $handle->describe();
            //
            self::assertSame("memoval2", $description->memo->getValue("memokey2"));
            $startWfAction = $description->schedule->action;
            self::assertSame("memoval3", $startWfAction->memo->getValue("memokey3"));
            //
            self::assertNotEquals($expectedUpdateTime, $description->info->lastUpdateAt);
            self::assertTrue($description->schedule->state->paused);
            self::assertEquals(2, $description->searchAttributes->count());
            self::assertSame('bar', $description->searchAttributes->getValue('foo'));
        } finally {
            $handle->delete();
        }
    }
}
