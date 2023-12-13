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
use Temporal\Client\Schedule\ScheduleOptions;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Mapper\ScheduleMapper;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Marshaller\ProtoToArrayConverter;

final class ScheduleClient implements ScheduleClientInterface
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

    public static function create(
        ServiceClientInterface $serviceClient,
        ClientOptions $options = null,
        DataConverterInterface $converter = null
    ): ScheduleClientInterface {
        return new self($serviceClient, $options, $converter);
    }

    public function createSchedule(
        Schedule $schedule,
        ?ScheduleOptions $options = null,
        ?string $scheduleId = null,
    ): ScheduleHandle {
        $scheduleId ??= Uuid::v4();
        $options ??= ScheduleOptions::new();
        $options->memo->setDataConverter($this->converter);
        $options->searchAttributes->setDataConverter($this->converter);

        $request = new CreateScheduleRequest();
        $request
            ->setRequestId(Uuid::v4())
            ->setNamespace($options->namespace)
            ->setScheduleId($scheduleId)
            ->setIdentity($this->clientOptions->identity);

        // Initial Patch
        $backfillRequests = [];
        foreach ($options->backfills as $period) {
            $period instanceof BackfillPeriod or throw new \InvalidArgumentException(
                'Backfill periods must be of type BackfillPeriod.'
            );

            $backfillRequests[] = (new BackfillRequest())
                ->setOverlapPolicy($period->overlapPolicy->value)
                ->setStartTime((new Timestamp())->setSeconds($period->startTime->getTimestamp()))
                ->setEndTime((new Timestamp())->setSeconds($period->endTime->getTimestamp()));
        }

        $initialPatch = (new SchedulePatch())->setBackfillRequest($backfillRequests);
        if ($options->triggerImmediately) {
            $overlap = $schedule->policies->overlapPolicy->value;
            $initialPatch
                ->setTriggerImmediately((new TriggerImmediatelyRequest())->setOverlapPolicy($overlap));
        }

        $mapper = new ScheduleMapper($this->converter, $this->marshaller);
        $scheduleMessage = $mapper->toMessage($schedule);

        $request
            ->setSchedule($scheduleMessage)
            ->setInitialPatch($initialPatch)
            ->setMemo((new Memo())->setFields($options->memo->toPayloadArray()))
            ->setSearchAttributes(
                (new SearchAttributes())->setIndexedFields($options->searchAttributes->toPayloadArray())
            );
        $this->client->CreateSchedule($request);

        return new ScheduleHandle(
            $this->client,
            $this->clientOptions,
            $this->converter,
            $this->marshaller,
            $this->protoConverter,
            $options->namespace,
            $scheduleId,
        );
    }

    public function getHandle(string $scheduleID, string $namespace = 'default'): ScheduleHandle
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
