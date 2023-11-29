<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Info;

use Temporal\Client\Schedule\Schedule;
use Temporal\DataConverter\EncodedCollection;
use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * Describes the current Schedule details from {@see ScheduleHandle::describe()}.
 *
 * @see \Temporal\Api\Workflowservice\V1\DescribeScheduleResponse
 */
final class ScheduleDescription
{
    /**
     * The complete current schedule details. This may not match the schedule as created because:
     *  - some types of schedule specs may get compiled into others (e.g. CronString into StructuredCalendarSpec)
     *  - some unspecified fields may be replaced by defaults
     *  - some fields in the state are modified automatically
     *  - the schedule may have been modified by UpdateSchedule or PatchSchedule
     */
    #[Marshal]
    public readonly Schedule $schedule;

    /**
     * Extra schedule state info.
     */
    #[Marshal]
    public readonly ScheduleInfo $info;

    /**
     * Non-indexed user supplied information.
     */
    #[Marshal]
    public readonly EncodedCollection $memo;

    /**
     * Indexed info that can be used in query of List schedules APIs.
     * The key and value type must be registered on Temporal server side.
     * Use GetSearchAttributes API to get valid key and corresponding value type.
     * For supported operations on different server versions see {@link https://docs.temporal.io/visibility}.
     */
    #[Marshal(name: 'search_attributes')]
    public readonly EncodedCollection $searchAttributes;

    /**
     * This value can be passed back to UpdateSchedule to ensure that the
     * schedule was not modified between a Describe and an Update, which could
     * lead to lost updates and other confusion.
     */
    #[Marshal(name: 'conflict_token')]
    public readonly string $conflictToken;

    /**
     * @internal The DTO is a result of a query, so it is not possible to create it manually.
     */
    public function __construct()
    {
    }
}
