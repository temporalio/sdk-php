<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\Query\V1\WorkflowQuery;

/**
 * @TODO: add implementation
 */
final class ReplayWorkflow
{
    public function start(HistoryEvent $event, ReplayWorkflowContext $context): void
    {
    }

    public function handleSignal(string $signalName, Payloads $input, int $eventId): void
    {
    }

    public function cancel(string $reason): void
    {
    }

    public function close(): void
    {
    }

    public function query(WorkflowQuery $query): ?Payloads
    {
        return null;
    }

    public function getWorkflowContext(): ReplayWorkflowContext
    {
        return new ReplayWorkflowContext();
    }
}
