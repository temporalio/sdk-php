<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus;

use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;

trait NexusHistoryAssertions
{
    protected static function countEvents(History $history, int $type): int
    {
        $count = 0;
        foreach ($history->getEvents() as $event) {
            if ($event->getEventType() === $type) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param list<int> $expectedTypes
     */
    protected static function assertContainsEvents(History $history, array $expectedTypes, string $message): void
    {
        $present = [];
        foreach ($history->getEvents() as $event) {
            $present[$event->getEventType()] = true;
        }
        foreach ($expectedTypes as $type) {
            self::assertArrayHasKey(
                $type,
                $present,
                $message . ' — missing event type ' . EventType::name($type),
            );
        }
    }

    protected static function historyContains(
        WorkflowClientInterface $client,
        WorkflowStubInterface $stub,
        int $eventType,
        float $timeout = 15.0,
    ): bool {
        $deadline = \microtime(true) + $timeout;
        do {
            foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
                if ($event->getEventType() === $eventType) {
                    return true;
                }
            }
            \usleep(500_000);
        } while (\microtime(true) < $deadline);

        return false;
    }

    /**
     * @return list<string>
     */
    protected static function historyEventNames(
        WorkflowClientInterface $client,
        WorkflowStubInterface $stub,
    ): array {
        $names = [];
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            $names[] = EventType::name($event->getEventType());
        }
        return $names;
    }
}
