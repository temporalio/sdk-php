<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Update\ScheduleClient;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\ScheduleHandle;
use Temporal\Client\Schedule\ScheduleOptions;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\ScheduleState;
use Temporal\Client\ScheduleClientInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Tests\Acceptance\App\TestCase;

class ScheduleClientTest extends TestCase
{
    #[Test]
    public function listSchedulesWithQuery(
        ScheduleClientInterface $client,
    ): void
    {
        /** @var list<ScheduleHandle> $handle */
        $handle = [];
        // Create a new schedules
        for ($i = 0; $i < 12; $i++) {
            $handle[] = $client->createSchedule(
                Schedule::new()
                    ->withAction(StartWorkflowAction::new('TestWorkflow'))
                    ->withSpec(ScheduleSpec::new()->withStartTime('+1 hour'))
                    ->withState(ScheduleState::new()->withPaused(true)),
                ScheduleOptions::new()
                    ->withSearchAttributes(
                        EncodedCollection::fromValues([
                            'bar' => $i % 2 === 0 ? 4242 : 24,
                        ])
                    )
            );
        }

        // Wait for schedules to be created
        $deadline = \microtime(true) + 5;
        check:
        $paginator = $client->listSchedules(
            pageSize: 10,
            query: 'bar = 4242'
        );
        if (\count($paginator->getPageItems()) < 6 && \microtime(true) < $deadline) {
            goto check;
        }

        try {
            $paginator = $client->listSchedules(
                pageSize: 5,
                query: 'bar = 4242'
            );

            $this->assertCount(5, $paginator->getPageItems());

            $next = $paginator->getNextPage();
            $this->assertNotNull($next);
            $this->assertCount(1, $next->getPageItems());
        } finally {
            foreach ($handle as $h) {
                $h->delete();
            }
        }
    }
}
