<?php

declare(strict_types=1);

namespace Temporal\Internal\Mapper;

use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Schedule\V1\Schedule;
use Temporal\Api\Schedule\V1\ScheduleInfo;
use Temporal\Api\Workflowservice\V1\DescribeScheduleResponse;
use Temporal\Client\Schedule\ScheduleDescription;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Client\Schedule\ScheduleInfo as ScheduleInfoDto;
use Temporal\Client\Schedule\Schedule as ScheduleDto;

final class ScheduleInfoMapper
{
    public function __construct(
        private readonly DataConverterInterface $converter,
    ) {
    }

    public function fromMessage(DescribeScheduleResponse $message): ScheduleDescription
    {
        return new ScheduleDescription(
            schedule: $this->prepareSchedule($message->getSchedule()),
            info: $this->prepareInfo($message->getInfo()),
            memo: $this->prepareMemo($message->getMemo()),
            searchAttributes: $this->prepareSearchAttributes($message->getSearchAttributes()),
            conflictToken: $message->getConflictToken(),
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

    // todo
    private function prepareSchedule(?Schedule $execution): ScheduleDto
    {
        \assert($execution !== null);

        return new ScheduleDto(
            id: $execution->getWorkflowId(),
            runId: $execution->getRunId()
        );
    }

    // todo
    private function prepareInfo(?ScheduleInfo $getAutoResetPoints): ScheduleInfoDto
    {
        \assert($getAutoResetPoints !== null);

        return new ScheduleInfoDto();
    }
}
