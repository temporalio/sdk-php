<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use Temporal\DataConverter\EncodedCollection;

/**
 * Describes the current Schedule details from {@see scheduleHandler::describe()}
 */
final class ScheduleDescription
{
    /**
     * @param Schedule $schedule Describes the modifiable fields of a schedule.
     * @param ScheduleInfo $info Extra information about the schedule.
     * @param EncodedCollection $memo Non-indexed user supplied information.
     * @param EncodedCollection $searchAttributes Indexed info that can be used in query of List schedules APIs.
     *        The key and value type must be registered on Temporal server side.
     *        Use GetSearchAttributes API to get valid key and corresponding value type.
     *        For supported operations on different server versions see {@link https://docs.temporal.io/visibility}.
     */
    public function __construct(
        public readonly Schedule $schedule,
        public readonly ScheduleInfo $info,
        public readonly EncodedCollection $memo,
        public readonly EncodedCollection $searchAttributes,
        public readonly string $conflictToken,
    ) {
    }
}
