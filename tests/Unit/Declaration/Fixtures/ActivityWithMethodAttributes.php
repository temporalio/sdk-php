<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Declaration\Fixtures;

use Temporal\Activity\Attribute\StartToCloseTimeout;
use Temporal\Activity\Attribute\Summary;
use Temporal\Activity\Attribute\TaskQueue;

class ActivityWithMethodAttributes
{
    #[TaskQueue('method-queue')]
    #[StartToCloseTimeout(60)]
    #[Summary('Method summary')]
    public function doWork(): void
    {
    }
}
