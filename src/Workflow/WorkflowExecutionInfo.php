<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use DateTimeInterface;
use JetBrains\PhpStorm\Immutable;
use Temporal\Common\WorkerVersionStamp;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Workflow\ResetPointInfo as ResetPointInfoDto;

/**
 * @see \Temporal\Api\Workflow\V1\WorkflowExecutionInfo
 * @psalm-immutable
 */
#[Immutable]
final class WorkflowExecutionInfo
{
    /**
     * @param array<ResetPointInfoDto> $autoResetPoints
     */
    public function __construct(
        public readonly WorkflowExecution $execution,
        public readonly WorkflowType $type,
        public readonly ?DateTimeInterface $startTime,
        public readonly ?DateTimeInterface $closeTime,
        public readonly WorkflowExecutionStatus $status,
        public readonly int $historyLength,
        public readonly ?string $parentNamespaceId,
        public readonly ?WorkflowExecution $parentExecution,
        public readonly ?DateTimeInterface $executionTime,
        public readonly EncodedCollection $memo,
        public readonly EncodedCollection $searchAttributes,
        public readonly array $autoResetPoints,
        public readonly string $taskQueue,
        public readonly int $stateTransitionCount,
        public readonly int $historySizeBytes,
        public readonly ?WorkerVersionStamp $mostRecentWorkerVersionStamp,
    ) {
    }
}
