<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Google\Protobuf\Internal\RepeatedField;
use InvalidArgumentException;
use Temporal\Api\Enums\V1\EventType;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Workflow\WorkflowExecution;

/**
 * Provides a wrapper with convenience methods over raw protobuf {@link History} object representing
 * workflow history
 */
final class WorkflowExecutionHistory
{
    private History $history;
    private WorkflowExecution $workflowExecution;

    public function __construct(History $history)
    {
        self::checkHistory($history);
        $this->history = $history;
        $this->workflowExecution = new WorkflowExecution('workflow_id_in_replay', 'run_id_in_replay',);
    }

    public function getHistory(): History
    {
        return $this->history;
    }

    public static function fromFile(string $filename): self
    {
        $json = file_get_contents($filename);
        return self::fromJson($json);
    }

    public static function fromJson(string $json): self
    {
        $json = (new HistoryJsonUtils())->prepareEnums($json);
        $history = (new History());
        $history->mergeFromJsonString($json, true);

        return new self($history);
    }

    public static function fromEvents(HistoryEvent ...$events): self
    {
        $events = new RepeatedField(HistoryEvent::class);
        foreach ($events as $index => $event) {
            $events->offsetSet($index, $event);
        }

        return new self(new History($events));
    }

    private static function checkHistory(History $history): void
    {
        $events = $history->getEvents();
        if ($events === null || $events->count() === 0) {
            throw new InvalidArgumentException('Empty history');
        }

        /** @var HistoryEvent $startedEvent */
        $startedEvent = $events->offsetGet(0);
        if ($startedEvent->getEventType() !== EventType::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED) {
            throw new InvalidArgumentException('First event is not WorkflowExecutionStarted');
        }

        if (!$startedEvent->hasWorkflowExecutionStartedEventAttributes()) {
            throw new InvalidArgumentException('First event is corrupted');
        }
    }

    /**
     * Returns a list of HistoryEvent objects.
     *
     * @return RepeatedField
     */
    public function getEvents(): RepeatedField
    {
        return $this->history->getEvents();
    }

    public function getLastEvent(): HistoryEvent
    {
        $events = $this->history->getEvents();

        return $events->offsetGet($events->count() - 1);
    }

    public function getEventsCount(): int
    {
        return $this->history->getEvents()->count();
    }

    public function getFirstEvent(): HistoryEvent
    {
        return $this->history->getEvents()->offsetGet(0);
    }

    public function getWorkflowExecution(): WorkflowExecution
    {
        return $this->workflowExecution;
    }

    public function isEmpty(): bool
    {
        return $this->history->getEvents() === null;
    }
}
