<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;
use Temporal\Common\WorkerVersionStamp;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Workflow\ResetPointInfo as ResetPointInfoDto;

/**
 * DTO that contains basic information about Workflow Execution.
 *
 * @see \Temporal\Api\Workflow\V1\WorkflowExecutionInfo
 * @psalm-immutable
 */
#[Immutable]
final class WorkflowExecutionInfo
{
    public function __construct(
        public readonly WorkflowExecution $execution,
        public readonly WorkflowType $type,
        public readonly ?\DateTimeInterface $startTime,
        public readonly ?\DateTimeInterface $closeTime,
        public readonly WorkflowExecutionStatus $status,
        public readonly int $historyLength,
        public readonly ?string $parentNamespaceId,
        public readonly ?WorkflowExecution $parentExecution,
        public readonly ?\DateTimeInterface $executionTime,
        public readonly EncodedCollection $memo,
        public readonly EncodedCollection $searchAttributes,

        /**
         * @var array<ResetPointInfoDto>
         */
        public readonly array $autoResetPoints,

        /**
         * @var non-empty-string
         */
        public readonly string $taskQueue,

        /**
         * @var int<1, max>
         */
        public readonly int $stateTransitionCount,

        /**
         * @var int<1, max>
         */
        public readonly int $historySizeBytes,

        /**
         * If set, the most recent worker version stamp that appeared in a workflow task completion
         * @deprecated
         */
        public readonly ?WorkerVersionStamp $mostRecentWorkerVersionStamp,

        /**
         * Workflow execution duration is defined as difference between close time and execution time.
         * This field is only populated if the workflow is closed.
         */
        public readonly ?\DateInterval $executionDuration,

        /**
         * Contains information about the root workflow execution.
         *
         * The root workflow execution is defined as follows:
         * 1. A workflow without parent workflow is its own root workflow.
         * 2. A workflow that has a parent workflow has the same root workflow as its parent workflow.
         *
         * Note: workflows continued as new or reseted may or may not have parents, check examples below.
         *
         * Examples:
         *   Scenario 1: Workflow W1 starts child workflow W2, and W2 starts child workflow W3.
         *     - The root workflow of all three workflows is W1.
         *   Scenario 2: Workflow W1 starts child workflow W2, and W2 continued as new W3.
         *     - The root workflow of all three workflows is W1.
         *   Scenario 3: Workflow W1 continued as new W2.
         *     - The root workflow of W1 is W1 and the root workflow of W2 is W2.
         *   Scenario 4: Workflow W1 starts child workflow W2, and W2 is reseted, creating W3
         *     - The root workflow of all three workflows is W1.
         *   Scenario 5: Workflow W1 is reseted, creating W2.
         *     - The root workflow of W1 is W1 and the root workflow of W2 is W2.
         */
        public readonly ?WorkflowExecution $rootExecution,

        /**
         * The first run ID in the execution chain.
         * Executions created via the following operations are considered to be in the same chain
         * - ContinueAsNew
         * - Workflow Retry
         * - Workflow Reset
         * - Cron Schedule
         */
        public readonly string $firstRunId,
    ) {}

    public function __debugInfo(): array
    {
        return [
            'execution' => $this->execution,
            'type' => $this->type,
            'startTime' => $this->startTime,
            'closeTime' => $this->closeTime,
            'status' => $this->status,
            'historyLength' => $this->historyLength,
            'parentNamespaceId' => $this->parentNamespaceId,
            'parentExecution' => $this->parentExecution,
            'executionTime' => $this->executionTime,
//            'memo' => $this->memo,
//            'searchAttributes' => $this->searchAttributes,
            'autoResetPoints' => $this->autoResetPoints,
            'taskQueue' => $this->taskQueue,
            'stateTransitionCount' => $this->stateTransitionCount,
            'historySizeBytes' => $this->historySizeBytes,
            'mostRecentWorkerVersionStamp' => $this->mostRecentWorkerVersionStamp,
            'executionDuration' => $this->executionDuration,
            'rootExecution' => $this->rootExecution,
            'firstRunId' => $this->firstRunId,
        ];
    }
}
