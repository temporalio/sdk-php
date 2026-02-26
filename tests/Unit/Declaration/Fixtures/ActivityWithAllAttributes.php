<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixtures;

use Temporal\Activity\ActivityCancellationType;
use Temporal\Activity\Attribute\CancellationType;
use Temporal\Activity\Attribute\HeartbeatTimeout;
use Temporal\Activity\Attribute\ScheduleToCloseTimeout;
use Temporal\Activity\Attribute\ScheduleToStartTimeout;
use Temporal\Activity\Attribute\StartToCloseTimeout;
use Temporal\Activity\Attribute\Summary;
use Temporal\Activity\Attribute\TaskQueue;
use Temporal\Common\Priority;
use Temporal\Common\RetryOptions;

#[TaskQueue('custom-queue')]
#[ScheduleToCloseTimeout(30)]
#[ScheduleToStartTimeout(10)]
#[StartToCloseTimeout(20)]
#[HeartbeatTimeout(5)]
#[CancellationType(ActivityCancellationType::WaitCancellationCompleted)]
#[RetryOptions]
#[Priority(priorityKey: 5)]
#[Summary('Do important work')]
class ActivityWithAllAttributes
{
}
