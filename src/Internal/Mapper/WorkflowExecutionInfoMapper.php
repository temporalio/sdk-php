<?php

declare(strict_types=1);

namespace Temporal\Internal\Mapper;

use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Common\V1\WorkerVersionStamp;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Workflow\V1\ResetPointInfo;
use Temporal\Api\Workflow\V1\ResetPoints;
use Temporal\Api\Workflow\V1\WorkflowExecutionInfo;
use Temporal\Common\WorkerVersionStamp as WorkerVersionStampDto;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Workflow\ResetPointInfo as ResetPointInfoDto;
use Temporal\Workflow\WorkflowExecution as WorkflowExecutionDto;
use Temporal\Workflow\WorkflowExecutionInfo as WorkflowExecutionInfoDto;
use Temporal\Workflow\WorkflowExecutionStatus;
use Temporal\Workflow\WorkflowType;

final class WorkflowExecutionInfoMapper
{
    public function __construct(
        private readonly DataConverterInterface $converter,
    ) {
    }

    public function fromMessage(WorkflowExecutionInfo $message): WorkflowExecutionInfoDto
    {
        $execution = $this->prepareWorkflowExecution($message->getExecution());
        \assert($execution !== null);

        $type = $message->getType();
        \assert($type !== null);
        $wfType = new WorkflowType();
        /** @psalm-suppress InaccessibleProperty */
        $wfType->name = $type->getName();

        return new WorkflowExecutionInfoDto(
            execution: $execution,
            type: $wfType,
            startTime: $message->getStartTime()?->toDateTime(),
            closeTime: $message->getCloseTime()?->toDateTime(),
            status: WorkflowExecutionStatus::from($message->getStatus()),
            historyLength: (int)$message->getHistoryLength(),
            parentNamespaceId: $message->getParentNamespaceId(),
            parentExecution: $this->prepareWorkflowExecution($message->getParentExecution()),
            executionTime: $message->getExecutionTime()?->toDateTime(),
            memo: $this->prepareMemo($message->getMemo()),
            searchAttributes: $this->prepareSearchAttributes($message->getSearchAttributes()),
            autoResetPoints: $this->prepareAutoResetPoints($message->getAutoResetPoints()),
            taskQueue: $message->getTaskQueue(),
            stateTransitionCount: (int)$message->getStateTransitionCount(),
            historySizeBytes: (int)$message->getHistorySizeBytes(),
            mostRecentWorkerVersionStamp: $this->prepareWorkerVersionStamp($message->getMostRecentWorkerVersionStamp()),
        );
    }

    public function prepareWorkerVersionStamp(?WorkerVersionStamp $versionStamp): ?WorkerVersionStampDto
    {
        return $versionStamp === null
            ? null
            : new WorkerVersionStampDto(
                buildId: $versionStamp->getBuildId(),
                bundleId: $versionStamp->getBundleId(),
                useVersioning: $versionStamp->getUseVersioning(),
            );
    }

    private function prepareMemo(?Memo $memo): EncodedCollection
    {
        if ($memo === null) {
            return EncodedCollection::fromValues([], $this->converter);
        }

        return EncodedCollection::fromPayloadCollection(
            $memo->getFields(),
            $this->converter,
        );
    }

    private function prepareSearchAttributes(?SearchAttributes $searchAttributes): EncodedCollection
    {
        if ($searchAttributes === null) {
            return EncodedCollection::fromValues([], $this->converter);
        }

        return EncodedCollection::fromPayloadCollection(
            $searchAttributes->getIndexedFields(),
            $this->converter,
        );
    }

    private function prepareWorkflowExecution(?WorkflowExecution $execution): ?WorkflowExecutionDto
    {
        if ($execution === null) {
            return null;
        }

        return new WorkflowExecutionDto(
            id: $execution->getWorkflowId(),
            runId: $execution->getRunId()
        );
    }

    /**
     * @return array<ResetPointInfoDto>
     */
    private function prepareAutoResetPoints(?ResetPoints $getAutoResetPoints): array
    {
        if ($getAutoResetPoints === null) {
            return [];
        }

        $resetPoints = [];
        foreach ($getAutoResetPoints->getPoints() as $point) {
            \assert($point instanceof ResetPointInfo);
            $resetPoints[] = new ResetPointInfoDto(
                binaryChecksum: $point->getBinaryChecksum(),
                runId: $point->getRunId(),
                firstWorkflowTaskCompletedId: (int)$point->getFirstWorkflowTaskCompletedId(),
                createTime: $point->getCreateTime()?->toDateTime(),
                expireTime: $point->getExpireTime()?->toDateTime(),
                resettable: $point->getResettable(),
            );
        }

        return $resetPoints;
    }
}
