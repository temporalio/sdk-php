<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;
use Throwable;

final class ActivityMocker
{
    private RoadRunnerActivityInvocationCache $cache;

    public function __construct()
    {
        $this->cache = RoadRunnerActivityInvocationCache::create();
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    public function expectCompletion(string $activityMethodName, $value): void
    {
        $this->cache->saveCompletion($activityMethodName, $value);
    }

    public function expectFailure(string $activityMethodName, Throwable $error): void
    {
        $this->cache->saveFailure($activityMethodName, $error);
    }
}
