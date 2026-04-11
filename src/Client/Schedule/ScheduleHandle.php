<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use Google\Protobuf\Timestamp;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Schedule\V1\BackfillRequest;
use Temporal\Api\Schedule\V1\SchedulePatch;
use Temporal\Api\Schedule\V1\TriggerImmediatelyRequest;
use Temporal\Api\Workflowservice\V1\DeleteScheduleRequest;
use Temporal\Api\Workflowservice\V1\DescribeScheduleRequest;
use Temporal\Api\Workflowservice\V1\ListScheduleMatchingTimesRequest;
use Temporal\Api\Workflowservice\V1\PatchScheduleRequest;
use Temporal\Api\Workflowservice\V1\UpdateScheduleRequest;
use Temporal\Client\ClientOptions;
use Temporal\Client\Common\ClientContextTrait;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\Schedule\Info\ScheduleDescription;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\Update\ScheduleUpdate;
use Temporal\Client\Schedule\Update\ScheduleUpdateInput;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Internal\Mapper\ScheduleMapper;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;

final class ScheduleHandle
{
    use ClientContextTrait;

    /**
     * Maximum number of retry attempts for conflict token mismatches when using a closure updater.
     */
    private const CONFLICT_TOKEN_MAX_RETRIES = 10;

    /**
     * Substring matched against a {@see ServiceClientException} message to detect a conflict
     * token mismatch returned by the CHASM scheduler. The full server error is
     * `serviceerror.NewFailedPrecondition("mismatched conflict token")` — see
     * `chasm/lib/scheduler/scheduler.go` (`ErrConflictTokenMismatch`) in temporalio/temporal.
     * The legacy V1 signal-based scheduler silently drops the update instead and does not
     * produce this error at all.
     */
    private const CONFLICT_TOKEN_ERROR_MARKER = 'conflict token';

    public function __construct(
        ServiceClientInterface $client,
        private readonly ClientOptions $clientOptions,
        private readonly DataConverterInterface $converter,
        private readonly MarshallerInterface $marshaller,
        private readonly ProtoToArrayConverter $protoConverter,
        private readonly string $namespace,
        private readonly string $id,
    ) {
        $this->client = $client;
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
     * There are two forms:
     *
     * - **Closure form** — the closure receives a {@see ScheduleUpdateInput} carrying the current
     *   {@see ScheduleDescription} and must return a {@see ScheduleUpdate}. The SDK automatically
     *   fetches a fresh description on every attempt and uses its conflict token, so concurrent
     *   updates from other clients are retried transparently up to {@see self::CONFLICT_TOKEN_MAX_RETRIES}
     *   times. If all retries are exhausted, a {@see ServiceClientException} with
     *   {@see StatusCode::FAILED_PRECONDITION} is raised.
     *
     * - **Direct form** — a pre-built {@see Schedule} is sent as-is. The optional `$conflictToken`
     *   argument is the opaque value from {@see ScheduleDescription::$conflictToken}; if supplied,
     *   the server rejects the update when the schedule has been modified since the describe that
     *   produced the token. No retry is performed; the caller handles conflicts.
     *
     * **IMPORTANT:** The closure may be invoked multiple times (once per retry), so it MUST be
     * idempotent and free of side effects outside of returning the {@see ScheduleUpdate}.
     * Do not increment counters, log business events, or mutate external state from inside it.
     *
     * Examples:
     *
     * Add a search attribute using the closure form (auto-retries on conflict):
     * ```
     * $handle->update(function (ScheduleUpdateInput $input): ScheduleUpdate {
     *     return ScheduleUpdate::new($input->description->schedule)
     *         ->withSearchAttributes(
     *             $input->description->searchAttributes
     *                 ->withValue('foo', 'bar')
     *                 ->withValue('bar', 42),
     *         );
     * });
     * ```
     *
     * Pause a described schedule with an explicit conflict token (no retry):
     * ```
     * $description = $handle->describe();
     * $schedule    = $description->schedule;
     * $handle->update(
     *     $schedule->withState($schedule->state->withPaused(true)),
     *     $description->conflictToken,
     * );
     * ```
     *
     * @param Schedule|\Closure(ScheduleUpdateInput): ScheduleUpdate $schedule The new Schedule to
     *        update to, or an idempotent closure that will be called with the current
     *        {@see ScheduleUpdateInput} and must return a {@see ScheduleUpdate}.
     * @param string|null $conflictToken Only valid with the direct form. Can be the value of
     *        {@see ScheduleDescription::$conflictToken}, causing the request to fail if the
     *        schedule has been modified between the {@see self::describe()} and this update.
     *        If missing, the schedule will be updated unconditionally. MUST be `null` when
     *        `$schedule` is a closure — in the closure form the token is managed internally by
     *        the retry loop; passing a non-null value throws {@see InvalidArgumentException}.
     *
     * @throws InvalidArgumentException When a non-null `$conflictToken` is passed together with a
     *         closure `$schedule`.
     * @throws ServiceClientException On a non-retryable server error, or after retries are
     *         exhausted in the closure form.
     */
    public function update(
        Schedule|\Closure $schedule,
        ?string $conflictToken = null,
    ): void {
        if ($schedule instanceof \Closure) {
            if ($conflictToken !== null) {
                throw new InvalidArgumentException(
                    'Passing a conflict token together with a closure updater is not supported: '
                    . 'in closure form the token is fetched from describe() on every retry. '
                    . 'Use the direct form `update(Schedule, ?string $conflictToken)` if you need '
                    . 'to pin the update to a specific token.',
                );
            }

            $this->updateWithClosure($schedule);
            return;
        }

        $this->doUpdate($schedule, $conflictToken);
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
     * @return \Countable&\Traversable<int, \DateTimeImmutable>
     */
    public function listScheduleMatchingTimes(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
    ): \Countable&\Traversable {
        $request = (new ListScheduleMatchingTimesRequest())
            ->setScheduleId($this->id)
            ->setNamespace($this->namespace)
            ->setStartTime((new Timestamp())->setSeconds($startTime->getTimestamp()))
            ->setEndTime((new Timestamp())->setSeconds($endTime->getTimestamp()));

        $response = $this->client->ListScheduleMatchingTimes($request);
        /** @var list<\DateTimeInterface> $list */
        $list = [];
        foreach ($response->getStartTime() as $timestamp) {
            $list[] = new \DateTimeImmutable("@{$timestamp->getSeconds()}");
        }

        return new \ArrayIterator($list);
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
                'Backfill periods must be of type BackfillPeriod.',
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

    private function updateWithClosure(\Closure $updater): void
    {
        for ($attempt = 0; $attempt < self::CONFLICT_TOKEN_MAX_RETRIES; $attempt++) {
            $description = $this->describe();
            $update = $updater(new ScheduleUpdateInput($description));
            $update instanceof ScheduleUpdate or throw new InvalidArgumentException(
                'Closure for the schedule update method must return a ScheduleUpdate.',
            );

            try {
                $this->doUpdate($update->schedule, $description->conflictToken, $update);
                return;
            } catch (ServiceClientException $e) {
                if ($e->getCode() !== StatusCode::FAILED_PRECONDITION
                    || !\str_contains($e->getMessage(), self::CONFLICT_TOKEN_ERROR_MARKER)
                ) {
                    throw $e;
                }

                // Conflict token mismatch — retry with a fresh describe
            }
        }

        throw new ServiceClientException((object) [
            'code' => StatusCode::FAILED_PRECONDITION,
            'details' => \sprintf(
                'Schedule update conflict token mismatch after %d retries',
                self::CONFLICT_TOKEN_MAX_RETRIES,
            ),
            'metadata' => [],
        ]);
    }

    private function doUpdate(Schedule $schedule, ?string $conflictToken, ?ScheduleUpdate $update = null): void
    {
        $request = (new UpdateScheduleRequest())
            ->setScheduleId($this->id)
            ->setNamespace($this->namespace)
            ->setConflictToken((string) $conflictToken)
            ->setIdentity($this->clientOptions->identity)
            ->setRequestId(Uuid::v4());

        // Search attributes from closure-based update
        if ($update?->searchAttributes !== null) {
            $update->searchAttributes->setDataConverter($this->converter);
            $payloads = $update->searchAttributes->toPayloadArray();
            $encodedSa = (new SearchAttributes())->setIndexedFields($payloads);
            $request->setSearchAttributes($encodedSa);
        }

        $mapper = new ScheduleMapper($this->converter, $this->marshaller);
        $scheduleMessage = $mapper->toMessage($schedule);
        $request->setSchedule($scheduleMessage);

        $this->client->UpdateSchedule($request);
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
