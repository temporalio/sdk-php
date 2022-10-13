<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Throwable;

class ReplayWorkflowTaskHandler
{
    private WorkflowServiceClient $workflowServiceClient;
    private ReplayWorkflowFactory $replayWorkflowFactory;

    public function __construct(
        WorkflowServiceClient $workflowServiceClient,
        ReplayWorkflowFactory $replayWorkflowFactory
    ) {
        $this->workflowServiceClient = $workflowServiceClient;
        $this->replayWorkflowFactory = $replayWorkflowFactory;
    }

    public function handleWorkflowTask(PollWorkflowTaskQueueResponse $workflowTask): WorkflowTaskHandlerResult
    {
        $historyIterator = new ServiceWorkflowHistoryIterator($this->workflowServiceClient, $workflowTask);
        $workflowRunTaskHandler = $this->getOrCreateWorkflowExecutor($workflowTask);
        try {
            $result = $workflowRunTaskHandler->handleWorkflowTask($workflowTask, $historyIterator);
            return $this->createCompletedWFTRequest($workflowTask, $result);
        } catch (\Throwable $e) {
            return $this->failureToWFTResult($workflowTask, $e);
        } finally {
            $workflowRunTaskHandler->close();
        }
    }

    private function getOrCreateWorkflowExecutor(
        PollWorkflowTaskQueueResponse $workflowTask
    ): ReplayWorkflowRunTaskHandler {
        $workflowType = $workflowTask->getWorkflowType()->getName();
        $events = $workflowTask->getHistory()->getEvents();
        if ($events->count() === 0 || $events->offsetGet(0)->getEventId() > 1) {
            $getHistoryRequest = (new GetWorkflowExecutionHistoryRequest())
                ->setExecution($workflowTask->getWorkflowExecution());
            /** @var GetWorkflowExecutionHistoryResponse $getHistoryResponse */
            $getHistoryResponse = $this->workflowServiceClient->GetWorkflowExecutionHistory($getHistoryRequest);
            $workflowTask
                ->setHistory($getHistoryResponse->getHistory())
                ->setNextPageToken($getHistoryResponse->getNextPageToken());
        }

        $replayWorkflow = $this->replayWorkflowFactory->getWorkflow($workflowType);
        return new ReplayWorkflowRunTaskHandler($replayWorkflow, $workflowTask);
    }

    /**
     * @TODO: add implementation
     */
    private function createCompletedWFTRequest(
        PollWorkflowTaskQueueResponse $workflowTask,
        WorkflowTaskResult $result
    ): WorkflowTaskHandlerResult {
        return new WorkflowTaskHandlerResult(
            'WorkflowType',
            new RespondWorkflowTaskCompletedRequest(),
            null,
            (new RespondQueryTaskCompletedRequest())
                ->setQueryResult(
                    (new Payloads())->setPayloads(
                        [(new Payload())->setData('"hello"')->setMetadata(['encoding' => 'json/plain'])]
                    )
                ),
            false
        );
    }

    /**
     * @TODO: add implementation
     */
    private function failureToWFTResult(
        PollWorkflowTaskQueueResponse $response,
        Throwable $e
    ): WorkflowTaskHandlerResult {
        return new WorkflowTaskHandlerResult(
            'WorkflowType',
            null,
            (new RespondWorkflowTaskFailedRequest()),
            null,
            false
        );
    }
}
