<?php

declare(strict_types=1);

namespace Temporal\Client;

use Generator;
use IteratorAggregate;
use Temporal\Api\Common\V1\DataBlob;
use Temporal\Api\History\V1\History;
use Temporal\Api\History\V1\HistoryEvent;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
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
     * @return Generator<int, DataBlob>
     */
    public function getRaws(): Generator
    {
        foreach ($this->paginator as $response) {
            /** @var DataBlob $history */
            foreach ($response->getRawHistory() as $history) {
                yield $history;
            }
        }
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
            \trap($history);
            if ($history === null) {
                return;
            }
            // /** @var iterable<HistoryEvent> $events */
            foreach ($history->getEvents() as $event) {
                // foreach ($events as $event) {
                    yield $event;
                // }
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
     * Stores workflow history to a file.
     */
    public function toFile(string $file)
    {
        // Check file
        if (!\is_dir(\dirname($file))) {
            throw new \RuntimeException(\sprintf('Directory "%s" does not exist.', \dirname($file)));
        }

        $events = \iterator_to_array($this->getEvents(), false);
        $history = new History();
        $history->setEvents($events);

        $data = $history->serializeToJsonString();
        \file_put_contents($file, $data);
    }
}
