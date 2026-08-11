<?php

declare(strict_types=1);

namespace Temporal\Testing;

use PHPUnit\Framework\Assert;
use Temporal\Worker\SearchAttributeInvocationCache\RoadRunnerSearchAttributeInvocationCache;
use Temporal\Worker\SearchAttributeInvocationCache\SearchAttributeInvocationCacheInterface;

final class SearchAttributeMocker
{
    private SearchAttributeInvocationCacheInterface $cache;

    public function __construct(?SearchAttributeInvocationCacheInterface $cache = null)
    {
        $this->cache = $cache ?? RoadRunnerSearchAttributeInvocationCache::create();
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * @param non-empty-string $name
     */
    public function wasUpserted(string $name): bool
    {
        return $this->cache->wasUpserted($name);
    }

    /**
     * @param non-empty-string $name
     */
    public function assertUpserted(string $name): void
    {
        Assert::assertTrue(
            $this->cache->wasUpserted($name),
            \sprintf('Expected search attribute "%s" to be upserted, but it was not.', $name),
        );
    }

    /**
     * @param non-empty-string $name
     */
    public function assertUpsertedValue(string $name, mixed $value): void
    {
        $entry = $this->cache->getUpsert($name);

        Assert::assertNotNull(
            $entry,
            \sprintf('Expected search attribute "%s" to be upserted, but it was not.', $name),
        );
        Assert::assertSame(
            SearchAttributeInvocationCacheInterface::OPERATION_SET,
            $entry['operation'] ?? null,
            \sprintf('Expected search attribute "%s" to be set, but it was unset.', $name),
        );

        $actual = $entry['value'] ?? null;
        $message = \sprintf('Search attribute "%s" value mismatch.', $name);

        if ($value instanceof \DateTimeInterface) {
            Assert::assertEquals($value, $actual, $message);

            return;
        }

        Assert::assertSame($value, $actual, $message);
    }

    /**
     * @param non-empty-string $name
     */
    public function assertUnset(string $name): void
    {
        $entry = $this->cache->getUpsert($name);

        Assert::assertNotNull(
            $entry,
            \sprintf('Expected search attribute "%s" to be unset, but it was not upserted at all.', $name),
        );
        Assert::assertSame(
            SearchAttributeInvocationCacheInterface::OPERATION_UNSET,
            $entry['operation'] ?? null,
            \sprintf('Expected search attribute "%s" to be unset, but it was set.', $name),
        );
    }

    /**
     * @param non-empty-string $name
     */
    public function assertNotUpserted(string $name): void
    {
        Assert::assertFalse(
            $this->cache->wasUpserted($name),
            \sprintf('Expected search attribute "%s" NOT to be upserted, but it was.', $name),
        );
    }

    /**
     * @param non-empty-string $name
     */
    public function getUpserted(string $name): mixed
    {
        $entry = $this->cache->getUpsert($name);

        return $entry === null ? null : ($entry['value'] ?? null);
    }

    /**
     * @return array<string, array{operation: string, type: string, value?: mixed}>
     */
    public function getUpsertedAttributes(): array
    {
        return $this->cache->all();
    }
}
