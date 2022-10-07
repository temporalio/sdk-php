<?php

declare(strict_types=1);

namespace Temporal\Testing\Replay;

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;

/** Replays a workflow given its history. Useful for backwards compatibility testing. */
final class WorkflowReplayer
{
    /** Use this constant as a query type to get a workflow stack trace. */
    public const QUERY_TYPE_STACK_TRACE = "__stack_trace";
    /** Replays workflow to the current state and returns empty result or error if replay failed. */
    private const QUERY_TYPE_REPLAY_ONLY = "__replay_only";

    private DataConverterInterface $dataConverter;
    private ReplayWorkflowTaskHandler $workflowTaskHandler;

    public function __construct(DataConverterInterface $dataConverter, ReplayWorkflowTaskHandler $workflowTaskHandler)
    {
        $this->dataConverter = $dataConverter;
        $this->workflowTaskHandler = $workflowTaskHandler;
    }

    public static function create(): self
    {
        return new self(DataConverter::createDefault(), new ReplayWorkflowTaskHandler());
    }

    /**
     * Replays workflow from a resource that contains a json serialized history.
     */
    public function replayWorkflowExecutionFromFile(string $filename): EncodedValues
    {
        $workflowExecutionHistory = WorkflowExecutionHistory::fromFile($filename);
        return $this->replayWorkflowExecution($workflowExecutionHistory);
    }

    private function replayWorkflowExecution(WorkflowExecutionHistory $workflowExecutionHistory, array $args = []): EncodedValues
    {
        $values = EncodedValues::fromValues($args, $this->dataConverter);
        $queryHelper = new QueryReplayHelper($this->workflowTaskHandler);
        $result = $queryHelper->queryWorkflowExecution(
            self::QUERY_TYPE_REPLAY_ONLY,
            $values->toPayloads(),
            $workflowExecutionHistory,
            ''
        );

        return EncodedValues::fromPayloads($result, $this->dataConverter);
    }
}
