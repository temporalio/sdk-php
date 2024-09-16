<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Update;

use Temporal\Client\Schedule\Info\ScheduleDescription;

/**
 * Parameter passed to a schedule updater.
 *
 * @see ScheduleHandle::update()
 */
final class ScheduleUpdateInput
{
    /**
     * @param ScheduleDescription $description Description fetched from the server before this update.
     *
     * @internal The DTO is created by the SDK and should not be created manually.
     */
    public function __construct(
        public readonly ScheduleDescription $description,
    ) {}
}
