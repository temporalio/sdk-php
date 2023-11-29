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
use Spiral\Attributes\AttributeReader;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Api\Schedule\V1\BackfillRequest;
use Temporal\Api\Schedule\V1\SchedulePatch;
use Temporal\Api\Schedule\V1\TriggerImmediatelyRequest;
use Temporal\Api\Workflowservice\V1\CreateScheduleRequest;
use Temporal\Api\Workflowservice\V1\ListSchedulesRequest;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\Schedule\BackfillPeriod;
use Temporal\Client\Schedule\Info\ScheduleListEntry;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\ScheduleHandle;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Mapper\ScheduleMapper;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;

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
     * Create a schedule and return its handle.
     *
     * @param Schedule $schedule Schedule to create.
     * @param non-empty-string|null $scheduleId Unique ID for the schedule. Will be generated as UUID if not provided.
     * @param bool $triggerImmediately Trigger one Action immediately on creating the Schedule.
     * @param iterable<BackfillPeriod> $backfills Runs though the specified time periods and takes Actions
     *         as if that time passed by right now, all at once. The overlap policy can be overridden for the scope
     *         of the ScheduleBackfill.
     * @param iterable $memo Optional non-indexed info that will be shown in list schedules.
     * @param iterable $searchAttributes Optional indexed info that can be used in query of List schedules APIs.
     *        The key and value type must be registered on Temporal server side. Use GetSearchAttributes API
     *        to get valid key and corresponding value type. For supported operations on different server
     *        versions see {@link https://docs.temporal.io/visibility}.
     */
    public function createSchedule(
        Schedule $schedule,
        ?string $scheduleId = null,

        // todo move into an options DTO?
        bool $triggerImmediately = false,
        iterable $backfills = [],
        iterable $memo = [],
        iterable $searchAttributes = [],
        string $namespace = 'default',
    ): ScheduleHandle {
        $requestId = Uuid::v4();
        $scheduleId ??= Uuid::v4();

        $request = new CreateScheduleRequest();
        $request
            ->setRequestId($requestId)
            ->setNamespace($namespace)
            ->setScheduleId($scheduleId)
            ->setIdentity($this->clientOptions->identity);

        // Initial Patch
        $backfillRequests = [];
        foreach ($backfills as $period) {
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
        if ($triggerImmediately) {
            $overlap = $schedule->policies->overlapPolicy->value;
            $initialPatch
                ->setTriggerImmediately((new TriggerImmediatelyRequest())->setOverlapPolicy($overlap));
        }

        $memoDto = (new Memo())
            ->setFields(EncodedCollection::fromValues($memo, $this->converter)->toPayloadArray());
        $searchAttributesDto = (new SearchAttributes())
            ->setIndexedFields(EncodedCollection::fromValues($searchAttributes, $this->converter)->toPayloadArray());

        $mapper = new ScheduleMapper($this->converter, $this->marshaller);
        $scheduleMessage = $mapper->toMessage($schedule);

        $request
            ->setSchedule($scheduleMessage)
            ->setInitialPatch($initialPatch)
            ->setMemo($memoDto)
            ->setSearchAttributes($searchAttributesDto);
        $this->client->CreateSchedule($request);

        return new ScheduleHandle(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $this->marshaller,
            $this->protoConverter,
            $namespace,
            $scheduleId,
        );
    }

    public function getHandler(string $scheduleID, string $namespace = 'default'): ScheduleHandle
    {
        return new ScheduleHandle(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $this->marshaller,
            $this->protoConverter,
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
                    \assert($message instanceof \Temporal\Api\Schedule\V1\ScheduleListEntry);
                    $values = $this->protoConverter->convert($message);
                    $dto = new ScheduleListEntry();

                    $page[] = $this->marshaller->unmarshal($values, $dto);
                }
                yield $page;

                $request->setNextPageToken($nextPageToken);
            } while ($nextPageToken !== '');
        };
        return Paginator::createFromGenerator($loader($request), null);
    }
}
