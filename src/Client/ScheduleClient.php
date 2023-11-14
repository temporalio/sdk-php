<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Google\Protobuf\Timestamp;
use Ramsey\Uuid\UuidInterface;
use Spiral\Attributes\AttributeReader;
use Temporal\Api\Schedule\V1\BackfillRequest;
use Temporal\Api\Schedule\V1\CalendarSpec;
use Temporal\Api\Schedule\V1\Schedule;
use Temporal\Api\Schedule\V1\ScheduleAction;
use Temporal\Api\Schedule\V1\ScheduleListEntry;
use Temporal\Api\Schedule\V1\SchedulePatch;
use Temporal\Api\Schedule\V1\SchedulePolicies;
use Temporal\Api\Schedule\V1\ScheduleSpec;
use Temporal\Api\Schedule\V1\ScheduleState;
use Temporal\Api\Schedule\V1\StructuredCalendarSpec;
use Temporal\Api\Workflowservice\V1\CreateScheduleRequest;
use Temporal\Api\Workflowservice\V1\ListSchedulesRequest;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\Schedule\BackfillPeriod;
use Temporal\Client\Schedule\ScheduleHandler;
use Temporal\Client\Schedule\ScheduleOverlapPolicy;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;
use Temporal\Internal\Support\DateInterval;

final class ScheduleClient
{
    private ServiceClientInterface $client;
    private ClientOptions $clientOptions;
    private DataConverterInterface $converter;
    private MarshallerInterface $marshaller;
    private ProtoToArrayConverter $protoConverter;

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions|null $options
     * @param DataConverterInterface|null $converter
     */
    public function __construct(
        ServiceClientInterface $serviceClient,
        ClientOptions $options = null,
        DataConverterInterface $converter = null
    ) {
        $this->client = $serviceClient;
        $this->clientOptions = $options ?? new ClientOptions();
        $this->converter = $converter ?? DataConverter::createDefault();
        $this->marshaller = new Marshaller(
            new AttributeMapperFactory(new AttributeReader()),
        );
        $this->protoConverter = new ProtoToArrayConverter($this->converter);
    }

    /**
     * @param ServiceClientInterface $serviceClient
     * @param ClientOptions|null $options
     * @param DataConverterInterface|null $converter
     * @return static
     */
    public static function create(
        ServiceClientInterface $serviceClient,
        ClientOptions $options = null,
        DataConverterInterface $converter = null
    ): self {
        return new self($serviceClient, $options, $converter);
    }

    /**
     * @psalm-import-type DateIntervalValue from DateInterval
     *
     * @param ScheduleSpec $spec Describes when Actions should be taken.
     * @param ScheduleAction $action Which Action to take.
     * @param ScheduleOverlapPolicy $overlap Controls what happens when an Action would be started by a Schedule
     *        at the same time that an older Action is still running.
     *        This can be changed after a Schedule has taken some Actions, and some changes might produce
     *        unintuitive results. In general, the later policy overrides the earlier policy.
     * @param DateIntervalValue|null $catchupWindow The Temporal Server might be down or unavailable at the time when
     *        a Schedule should take an Action. When the Server comes back up, CatchupWindow controls which missed
     *        Actions should be taken at that point. The default is one minute, which means that the Schedule attempts
     *        to take any Actions that wouldn't be more than one minute late. It takes those Actions according to the
     *        Overlap. An outage that lasts longer than the Catchup Window could lead to missed Actions.
     * @param bool $pauseOnFailure When an Action times out or reaches the end of its Retry Policy the Schedule will
     *        pause. With {@see ScheduleOverlapPolicy::AllowAll}, this pause might not apply to the next Action,
     *        because the next Action might have already started previous to the failed one finishing.
     *        Pausing applies only to Actions that are scheduled to start after the failed one finishes.
     * @param int<0, max> $remainingActions Limit the number of Actions to take.
     *        This number is decremented after each Action is taken, and Actions are not
     *        taken when the number is `0` (unless {@see ScheduleHandler::trigger()} is called).
     * @param bool $triggerImmediately Trigger one Action immediately on creating the Schedule.
     * @param iterable<BackfillPeriod> $scheduleBackfill Runs though the specified time periods and takes Actions
     *        as if that time passed by right now, all at once. The overlap policy can be overridden for the scope
     *        of the ScheduleBackfill.
     * @param string|UuidInterface|null $scheduleId Will be generated as UUID if not provided.
     * @param iterable $memo Optional non-indexed info that will be shown in list schedules.
     * @param iterable $searchAttributes Optional indexed info that can be used in query of List schedules APIs.
     *        The key and value type must be registered on Temporal server side. Use GetSearchAttributes API
     *        to get valid key and corresponding value type. For supported operations on different server
     *        versions see {@link https://docs.temporal.io/visibility}.
     */
    public function createSchedule(
        ScheduleSpec $spec,
        ScheduleAction $action,
        ScheduleOverlapPolicy $overlap = ScheduleOverlapPolicy::Skip,
        null|string|int|float|\DateInterval $catchupWindow = null, // todo duration
        bool $pauseOnFailure = false,
        int $remainingActions = 0,
        bool $triggerImmediately = false,
        iterable $scheduleBackfill = [],
        string $namespace = 'default',
        string|UuidInterface $scheduleId = null,
        iterable $memo = [],
        iterable $searchAttributes = [],
    ): ScheduleHandler {
        $requestId = Uuid::v4();
        $scheduleId ??= Uuid::v4();

        $request = new CreateScheduleRequest();
        $request
            ->setRequestId($requestId)
            ->setNamespace($namespace)
            ->setScheduleId((string)$scheduleId)
            ->setIdentity($this->clientOptions->identity);

        // Initial Patch
        $backfillRequests = [];
        foreach ($scheduleBackfill as $period) {
            $period instanceof BackfillPeriod or throw new \InvalidArgumentException(
                'Backfill periods must be of type BackfillPeriod.'
            );

            $backfillRequests[] = (new BackfillRequest())
                ->setOverlapPolicy($period->overlapPolicy->value)
                ->setStartTime((new Timestamp())->setSeconds($period->startTime->getTimestamp()))
                ->setEndTime((new Timestamp())->setSeconds($period->endTime->getTimestamp()));
        }
        $initialPatch = (new SchedulePatch())
            ->setBackfillRequest($backfillRequests);
            // ->setTriggerImmediately($triggerImmediately);

        $scheduleDto = (new Schedule())
            ->setPolicies(
                (new SchedulePolicies())
                    ->setCatchupWindow(
                        DateInterval::toDuration(
                            DateInterval::parse($catchupWindow ?? '1 minute'),
                        )
                    )
                    ->setOverlapPolicy($overlap->value)
                    ->setPauseOnFailure($pauseOnFailure)
            );


        $scheduleDto
            ->setAction(
                (new ScheduleAction())
                    ->setStartWorkflow(/*NewWorkflowExecutionInfo*/)
            )
            ->setSpec(
                (new ScheduleSpec())
                    ->setCalendar(
                        [
                            (new CalendarSpec())->setComment()->setYear()->setMonth()->setDayOfMonth()->setHour(
                            )->setMinute()->setSecond()
                        ]
                    )
                    ->setCronString()
                    ->setStartTime()
                    ->setEndTime()
                    ->setInterval()
                    ->setStructuredCalendar([
                        (new StructuredCalendarSpec())->setYear()->setMonth()->setDayOfMonth()->setHour()->setMinute(
                        )->setSecond()
                    ])
                    ->setExcludeStructuredCalendar()
                    ->setJitter()
                    ->setTimezoneData()
                    ->setTimezoneName()
            )
            ->setState(
                (new ScheduleState())
                    ->setNotes()
                    ->setLimitedActions()
                    ->setRemainingActions()
                    ->setPaused()
            );
        // ->setMemo((new Memo())->setFields())
        // ->setInitialPatch((new SchedulePatch())->setPause()
        //     ->setBackfillRequest(/*BackfillRequest[]*/)
        //     ->setTriggerImmediately(/*TriggerImmediatelyRequest*/))
        // ->setSearchAttributes()

        $request
            ->setSchedule($scheduleDto)
            ->setInitialPatch($initialPatch);
        $response = $this->client->CreateSchedule($request);

        return new ScheduleHandler(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $namespace,
            $scheduleId,
            $response->getConflictToken(),
        );
    }

    public function getHandler(string $scheduleID, string $namespace = 'default'): ScheduleHandler
    {
        return new ScheduleHandler(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $namespace,
            $scheduleID,
        );
    }

    /**
     * List all schedules in a namespace.
     *
     * @param non-empty-string $namespace
     * @param int<0, max> $pageSize Maximum number of Schedule info per page.
     *
     * @return Paginator<ScheduleListEntry>
     */
    public function listSchedules(
        string $namespace = 'default',
        int $pageSize = 0,
    ): Paginator {
        // Build request
        $request = (new ListSchedulesRequest())
            ->setNamespace($namespace)
            ->setMaximumPageSize($pageSize);

        $loader = function (ListSchedulesRequest $request): \Generator {
            do {
                $response = $this->client->ListSchedules($request);
                $nextPageToken = $response->getNextPageToken();

                $page = [];
                foreach ($response->getSchedules() as $message) {
                    \assert($message instanceof ScheduleListEntry);
                    $values = $this->protoConverter->convert($message);
                    $dto = new \Temporal\Client\Schedule\ScheduleListEntry();

                    $page[] = $this->marshaller->unmarshal($values, $dto);
                }
                yield $page;

                $request->setNextPageToken($nextPageToken);
            } while ($nextPageToken !== '');
        };
        return Paginator::createFromGenerator($loader($request), null);
    }
}
