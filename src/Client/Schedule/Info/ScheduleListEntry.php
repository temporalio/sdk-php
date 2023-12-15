<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Info;

use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * ScheduleListEntry is returned by {@see \Temporal\Client\ScheduleClientInterface::listSchedules()}.
 *
 * @see \Temporal\Api\Schedule\V1\ScheduleListEntry
 */
final class ScheduleListEntry
{
    #[Marshal(name: 'info')]
    public readonly ScheduleListInfo $info;

    /**
     * The business identifier of the schedule.
     */
    #[Marshal(name: 'schedule_id')]
    public readonly string $scheduleId;

    /**
     * Non-indexed user supplied information.
     */
    #[Marshal(name: 'memo')]
    public readonly EncodedCollection $memo;

    /**
     * Indexed info that can be used in query of List schedules APIs. The key and value type must be registered on
     * Temporal server side. Use GetSearchAttributes API to get valid key and corresponding value type.
     * For supported operations on different server versions see {@link https://docs.temporal.io/visibility}.
     */
    #[Marshal(name: 'search_attributes')]
    public readonly EncodedCollection $searchAttributes;

    /**
     * @internal The DTO is a result of a query, so it is not possible to create it manually.
     */
    public function __construct()
    {
    }
}
