<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use ArrayIterator;
use Countable;
use DateTimeImmutable;
use DateTimeInterface;
use Google\Protobuf\Timestamp;
use Temporal\Api\Schedule\V1\BackfillRequest;
use Temporal\Api\Schedule\V1\SchedulePatch;
use Temporal\Api\Schedule\V1\TriggerImmediatelyRequest;
use Temporal\Api\Workflowservice\V1\DeleteScheduleRequest;
use Temporal\Api\Workflowservice\V1\DescribeScheduleRequest;
use Temporal\Api\Workflowservice\V1\ListScheduleMatchingTimesRequest;
use Temporal\Api\Workflowservice\V1\PatchScheduleRequest;
use Temporal\Api\Workflowservice\V1\UpdateScheduleRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\Schedule\Info\ScheduleDescription;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Internal\Mapper\ScheduleMapper;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;
use Traversable;

final class ScheduleHandle
{
    public function __construct(
        private readonly ServiceClientInterface $client,
        private readonly ClientOptions $clientOptions,
        private readonly DataConverterInterface $converter,
        private readonly MarshallerInterface $marshaller,
        private readonly ProtoToArrayConverter $protoConverter,
        private readonly string $namespace,
        private readonly string $id,
    ) {
    }

    /**
     * GetID returns the schedule ID associated with this handle.
     */
    public function getID(): string
    {
        return $this->id;
    }

    /**
     * Update the Schedule.
     *
     * NOTE: If two Update calls are made in parallel to the same Schedule there is the potential
     * for a race condition. Use $conflictToken to avoid this.
     *
     * @param Schedule $schedule The new Schedule to update to.
     * @param string|null $conflictToken Can be the value of {@see ScheduleDescription::$conflictToken},
     *        which will cause this request to fail if the schedule has been modified
     *        between the {@see self::describe()} and this Update.
     *        If missing, the schedule will be updated unconditionally.
     */
    public function update(
        Schedule $schedule,
        ?string $conflictToken = null,
    ): void {
        $mapper = new ScheduleMapper($this->converter, $this->marshaller);
        $scheduleMessage = $mapper->toMessage($schedule);

        $request = (new UpdateScheduleRequest())
            ->setScheduleId($this->id)
            ->setNamespace($this->namespace)
            ->setConflictToken($conflictToken)
            ->setIdentity($this->clientOptions->identity)
            ->setSchedule($scheduleMessage);

        $this->client->UpdateSchedule($request);
    }

    /**
     * Describe fetches the Schedule's description from the Server
     */
    public function describe(): ScheduleDescription
    {
        $request = (new DescribeScheduleRequest())
            ->setScheduleId($this->id)
            ->setNamespace($this->namespace);

        $response = $this->client->DescribeSchedule($request);
        $values = $this->protoConverter->convert($response);
        $dto = new ScheduleDescription();

        return $this->marshaller->unmarshal($values, $dto);
    }

    /**
     * Lists matching times within a range.
     *
     * @return Countable&Traversable<int, DateTimeImmutable>
     */
    public function listScheduleMatchingTimes(
        DateTimeInterface $startTime,
        DateTimeInterface $endTime,
    ): Countable&Traversable {
        $request = (new ListScheduleMatchingTimesRequest())
            ->setScheduleId($this->id)
            ->setNamespace($this->namespace)
            ->setStartTime((new Timestamp())->setSeconds($startTime->getTimestamp()))
            ->setEndTime((new Timestamp())->setSeconds($endTime->getTimestamp()));

        $response = $this->client->ListScheduleMatchingTimes($request);
        /** @var list<DateTimeInterface> $list */
        $list = [];
        foreach ($response->getStartTime() as $timestamp) {
            \assert($timestamp instanceof Timestamp);

            $list[] = new \DateTimeImmutable('@' . $timestamp->getSeconds());
        }

        return new ArrayIterator($list);
    }

    /**
     * Backfill the schedule by going though the specified time periods and taking Actions as if that
     * time passed by right now, all at once.
     *
     * @param iterable<BackfillPeriod> $periods Time periods to backfill the schedule.
     */
    public function backfill(iterable $periods): void
    {
        $backfill = [];
        foreach ($periods as $period) {
            $period instanceof BackfillPeriod or throw new InvalidArgumentException(
                'Backfill periods must be of type BackfillPeriod.'
            );

            $backfill[] = (new BackfillRequest())
                ->setOverlapPolicy($period->overlapPolicy->value)
                ->setStartTime((new Timestamp())->setSeconds($period->startTime->getTimestamp()))
                ->setEndTime((new Timestamp())->setSeconds($period->endTime->getTimestamp()));
        }

        $request = $this->patch((new SchedulePatch())->setBackfillRequest($backfill));
        $this->client->PatchSchedule($request);
    }

    /**
     * Trigger an Action to be taken immediately. Will override the schedules default policy
     * with the one specified here. If overlap is {@see ScheduleOverlapPolicy::Unspecified} the Schedule
     * policy will be used.
     *
     * @param ScheduleOverlapPolicy $overlapPolicy If specified, policy to override the Schedules
     *        default overlap policy.
     */
    public function trigger(ScheduleOverlapPolicy $overlapPolicy = ScheduleOverlapPolicy::Unspecified): void
    {
        $request = $this->patch(
            (new SchedulePatch())->setTriggerImmediately(
                (new TriggerImmediatelyRequest())->setOverlapPolicy($overlapPolicy->value),
            ),
        );
        $this->client->PatchSchedule($request);
    }

    /**
     * Pause the Schedule will also overwrite the Schedules current note with the new note.
     *
     * @param string $note Informative human-readable message with contextual notes.
     * @psalm-assert non-empty-string $note
     */
    public function pause(string $note = 'Paused via PHP SDK'): void
    {
        $note === '' and throw new InvalidArgumentException('Pause note cannot be empty.');

        $request = $this->patch((new SchedulePatch())->setPause($note));
        $this->client->PatchSchedule($request);
    }

    /**
     * Unpause the Schedule will also overwrite the Schedules current note with the new note.
     *
     * @param string $note Informative human-readable message with contextual notes.
     * @psalm-assert non-empty-string $note
     */
    public function unpause(string $note = 'Unpaused via PHP SDK'): void
    {
        $note === '' and throw new InvalidArgumentException('Unpause note cannot be empty.');

        $request = $this->patch((new SchedulePatch())->setUnpause($note));
        $this->client->PatchSchedule($request);
    }

    /**
     * Delete the Schedule.
     */
    public function delete(): void
    {
        $request = (new DeleteScheduleRequest())
            ->setNamespace($this->namespace)
            ->setScheduleId($this->id)
            ->setIdentity($this->clientOptions->identity);

        $this->client->DeleteSchedule($request);
    }

    private function patch(SchedulePatch $patch): PatchScheduleRequest
    {
        return (new PatchScheduleRequest())
            ->setScheduleId($this->id)
            ->setNamespace($this->namespace)
            ->setRequestId(Uuid::v4())
            ->setPatch($patch);
    }
}
