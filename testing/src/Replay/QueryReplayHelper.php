<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use RuntimeException;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\History\V1\History;
use Temporal\Api\Query\V1\WorkflowQuery;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use Temporal\Exception\IllegalStateException;

final class QueryReplayHelper
{
    private ReplayWorkflowTaskHandler $workflowTaskHandler;

    public function __construct(ReplayWorkflowTaskHandler $workflowTaskHandler)
    {
        $this->workflowTaskHandler = $workflowTaskHandler;
    }

    public function queryWorkflowExecution(
        string $queryType,
        ?Payloads $payloads,
        WorkflowExecutionHistory $workflowExecutionHistory,
        string $nextPageToken
    ): Payloads {
        $query = (new WorkflowQuery())->setQueryType($queryType);
        if ($payloads !== null) {
            $query->setQueryArgs($payloads);
        }

        $task = (new PollWorkflowTaskQueueResponse())
            ->setWorkflowExecution($workflowExecutionHistory->getWorkflowExecution()->toProtoWorkflowExecution())
            ->setStartedEventId(PHP_INT_MAX)
            ->setPreviousStartedEventId(PHP_INT_MAX)
            ->setNextPageToken($nextPageToken)
            ->setQuery($query);

        $events = $workflowExecutionHistory->getEvents();
        $startedEvent = $workflowExecutionHistory->getFirstEvent();
        if (!$startedEvent->hasWorkflowExecutionStartedEventAttributes()) {
            throw new IllegalStateException(
                'First event of the history is not WorkflowExecutionStarted: ' . $startedEvent->getEventType()
            );
        }
        $startedAttributes = $startedEvent->getWorkflowExecutionStartedEventAttributes();
        $workflowType = $startedAttributes->getWorkflowType();
        $task->setWorkflowType($workflowType);
        $task->setHistory((new History(['events' => $events])));

        $result = $this->workflowTaskHandler->handleWorkflowTask($task);
        if ($result->getQueryCompleted() != null) {
            $request = $result->getQueryCompleted();
            if ($request->getErrorMessage() !== '') {
                throw new RuntimeException(); // add message
            }

            if ($request->hasQueryResult()) {
                return $request->getQueryResult();
            } else {
                return new Payloads();
            }
        }

        throw new RuntimeException('Query returned wrong response');
    }
}
