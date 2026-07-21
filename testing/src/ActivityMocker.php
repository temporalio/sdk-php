<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Temporal\Worker\ActivityInvocationCache\ActivityInvocationCacheInterface;
use Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache;

final class ActivityMocker
{
    private ActivityInvocationCacheInterface $cache;

    public function __construct(?ActivityInvocationCacheInterface $cache = null)
    {
        $this->cache = $cache ?? RoadRunnerActivityInvocationCache::create();
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * @param non-empty-string $activityMethodName
     */
    public function expectCompletion(string $activityMethodName, mixed $value): void
    {
        $this->cache->saveCompletion($activityMethodName, $value);
    }

    /**
     * @param non-empty-string $activityMethodName
     */
    public function expectFailure(string $activityMethodName, \Throwable $error): void
    {
        $this->cache->saveFailure($activityMethodName, $error);
    }

    /**
     * @param non-empty-string $activityMethodName
     * @param list<mixed> $values
     */
    public function expectConsecutiveCompletions(string $activityMethodName, array $values): void
    {
        $this->cache->saveConsecutiveCompletions($activityMethodName, $values);
    }

    /**
     * @param non-empty-string $activityMethodName
     * @param list<mixed> $args
     */
    public function expectCompletionWhen(string $activityMethodName, array $args, mixed $value): void
    {
        $this->cache->saveCompletionWhen($activityMethodName, $args, $value);
    }
}
