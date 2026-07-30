<?php

declare(strict_types=1);

namespace Temporal\Testing;

use PHPUnit\Framework\Assert;
use Temporal\Worker\ChildWorkflowInvocationCache\ChildWorkflowInvocationCacheInterface;
use Temporal\Worker\ChildWorkflowInvocationCache\RoadRunnerChildWorkflowInvocationCache;

final class WorkflowMocker
{
    private ChildWorkflowInvocationCacheInterface $cache;

    public function __construct(?ChildWorkflowInvocationCacheInterface $cache = null)
    {
        $this->cache = $cache ?? RoadRunnerChildWorkflowInvocationCache::create();
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * @param non-empty-string $workflowType
     * @param mixed $value Result the mocked child workflow returns: anything the DataConverter
     *        can encode (scalar, array, DTO, ...) or an EncodedValues for pre-encoded payloads.
     */
    public function expectCompletion(string $workflowType, mixed $value): void
    {
        $this->cache->saveCompletion($workflowType, $value);
    }

    /**
     * @param non-empty-string $workflowType
     */
    public function expectFailure(string $workflowType, \Throwable $error): void
    {
        $this->cache->saveFailure($workflowType, $error);
    }

    /**
     * @param non-empty-string $workflowType
     * @param array<array-key, mixed> $args
     */
    public function expectCompletionWhen(string $workflowType, array $args, mixed $value): void
    {
        $this->cache->saveCompletionWhen($workflowType, $args, $value);
    }

    /**
     * @param non-empty-string $workflowType
     */
    public function wasInvoked(string $workflowType): bool
    {
        return $this->cache->wasInvoked($workflowType);
    }

    /**
     * @param non-empty-string $workflowType
     */
    public function assertInvoked(string $workflowType): void
    {
        Assert::assertTrue(
            $this->cache->wasInvoked($workflowType),
            \sprintf('Expected child workflow "%s" mock to be invoked, but it was not.', $workflowType),
        );
    }

    /**
     * @param non-empty-string $workflowType
     */
    public function assertNotInvoked(string $workflowType): void
    {
        Assert::assertFalse(
            $this->cache->wasInvoked($workflowType),
            \sprintf('Expected child workflow "%s" mock NOT to be invoked, but it was.', $workflowType),
        );
    }

    /**
     * @param non-empty-string $changeId
     */
    public function expectVersion(string $changeId, int $version): void
    {
        $this->cache->saveVersion($changeId, $version);
    }

    public function expectSideEffect(mixed $value): void
    {
        $this->cache->saveSideEffect($value);
    }
}
