<?php

declare(strict_types=1);

namespace Temporal\Client\Mapper;

use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Common\V1\WorkflowExecution;
use Temporal\Api\Workflow\V1\ResetPointInfo;
use Temporal\Api\Workflow\V1\ResetPoints;
use Temporal\Api\Workflow\V1\WorkflowExecutionInfo;
use Temporal\Client\DTO\ResetPointInfo as ResetPointInfoDto;
use Temporal\Client\DTO\WorkflowExecutionInfo as WorkflowExecutionInfoDto;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Workflow\WorkflowExecution as WorkflowExecutionDto;
use Temporal\Workflow\WorkflowType;

final class WorkflowExecutionInfoMapper
{
    public function __construct(
        private DataConverterInterface $converter,
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
            status: $message->getStatus(),
            historyLength: (int)$message->getHistoryLength(),
            parentNamespaceId: $message->getParentNamespaceId(),
            parentExecution: $this->prepareWorkflowExecution($message->getParentExecution()),
            executionTime: $message->getExecutionTime()?->toDateTime(),
            memo: $this->prepareMemo($message->getMemo()),
            searchAttributes: $this->prepareSearchAttributes($message->getSearchAttributes()),
            autoResetPoints: $this->prepareAutoResetPoints($message->getAutoResetPoints()),
            taskQueue: $message->getTaskQueue(),
            stateTransitionCount: $message->getStateTransitionCount(),
        );
    }

    private function prepareMemo(?Memo $memo): EncodedValues
    {
        if ($memo === null) {
            return EncodedValues::fromValues([], $this->converter);
        }

        return EncodedValues::fromPayloads(
            (new Payloads())->setPayloads(\iterator_to_array($memo->getFields())),
            $this->converter,
        );
    }

    private function prepareSearchAttributes(?SearchAttributes $searchAttributes): EncodedValues
    {
        if ($searchAttributes === null) {
            return EncodedValues::fromValues([], $this->converter);
        }

        return EncodedValues::fromPayloads(
            (new Payloads())->setPayloads(\iterator_to_array($searchAttributes->getIndexedFields())),
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
