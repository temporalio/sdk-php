<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule;

final class Schedule
{
    protected ScheduleSpec $spec;
    protected ScheduleAction $action = null;
    protected SchedulePolicies $policies = null;
    protected ScheduleState $state = null;
}
