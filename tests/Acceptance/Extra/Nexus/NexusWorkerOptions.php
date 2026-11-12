<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus;

use Temporal\Worker\WorkerOptions;

final class NexusWorkerOptions
{
    public const PRE_CANCEL_TIMER_SECONDS = 1;

    public static function default(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }
}
