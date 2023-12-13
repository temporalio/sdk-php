<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Client\Schedule\Info\ScheduleListEntry;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\ScheduleHandle;
use Temporal\Client\Schedule\ScheduleOptions;

interface ScheduleClientInterface
{
    /**
     * Create a schedule and return its handle.
     *
     * @param Schedule $schedule Schedule to create.
     * @param ScheduleOptions|null $options Options for creating the schedule.
     * @param non-empty-string|null $scheduleId Unique ID for the schedule. Will be generated as UUID if not provided.
     */
    public function createSchedule(
        Schedule $schedule,
        ?ScheduleOptions $options = null,
        ?string $scheduleId = null,
    ): ScheduleHandle;

    /**
     * Get a schedule handle to interact with an existing schedule.
     *
     * @param non-empty-string $scheduleID
     * @param non-empty-string $namespace
     */
    public function getHandle(string $scheduleID, string $namespace = 'default'): ScheduleHandle;

    /**
     * List all schedules in a namespace.
     *
     * @param non-empty-string $namespace
     * @param int<0, max> $pageSize Maximum number of Schedule info per page.
     *
     * @return Paginator<ScheduleListEntry>
     */
    public function listSchedules(string $namespace = 'default', int $pageSize = 0,): Paginator;
}
