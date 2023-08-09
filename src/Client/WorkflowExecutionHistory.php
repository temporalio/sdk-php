<?php

declare(strict_types=1);

namespace Temporal\Client;

use Generator;
use IteratorAggregate;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Testing\Replay\WorkflowReplayer;
use Traversable;

/**
 * Provides a wrapper with convenience methods over raw protobuf object representing
 * workflow history {@see History}
 *
 * @implements IteratorAggregate<int, HistoryEvent>
 * @internal
 */
final class WorkflowExecutionHistory implements IteratorAggregate
{
    /**
     * @param Paginator<GetWorkflowExecutionHistoryResponse> $paginator
     */
    public function __construct(
        private readonly Paginator $paginator
    ) {
    }

    /**
     * Returns an iterator of HistoryEvent objects.
     *
     * @return Generator<int, HistoryEvent>
     */
    public function getEvents(): Generator
    {
        foreach ($this->paginator as $response) {
            $history = $response->getHistory();
            if ($history === null) {
                return;
            }
            /** @var HistoryEvent $event */
            foreach ($history->getEvents() as $event) {
                yield $event;
            }
        }
    }

    /**
     * @return Traversable<int, HistoryEvent>
     */
    public function getIterator(): Traversable
    {
        return $this->getEvents();
    }

    /**
     * Returns {@see History} object with all the events inside.
     * The returned object may be used to replay the workflow via {@see WorkflowReplayer::replayHistory()}.
     */
    public function getHistory(): History
    {
        $events = \iterator_to_array($this->getEvents(), false);
        $history = new History();
        $history->setEvents($events);

        return $history;
    }
}
