<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

use Temporal\Client\Schedule\Spec\SchedulePolicies;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\ScheduleState;
use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * @see \Temporal\Api\Schedule\V1\Schedule
 */
final class Schedule
{
    #[Marshal]
    public readonly ScheduleSpec $spec;

    #[Marshal]
    public readonly ScheduleAction $action;

    #[Marshal]
    public readonly SchedulePolicies $policies;

    #[Marshal]
    public readonly ScheduleState $state;
}
