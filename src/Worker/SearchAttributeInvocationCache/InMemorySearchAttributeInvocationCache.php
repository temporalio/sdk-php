<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\SearchAttributeInvocationCache;

final class InMemorySearchAttributeInvocationCache implements SearchAttributeInvocationCacheInterface
{
    /**
     * @var array<string, array{operation: string, type: string, value?: mixed}>
     */
    private array $upserts = [];

    public function clear(): void
    {
        $this->upserts = [];
    }

    public function recordUpsert(string $name, array $entry): void
    {
        $this->upserts[$name] = $entry;
    }

    public function wasUpserted(string $name): bool
    {
        return \array_key_exists($name, $this->upserts);
    }

    public function getUpsert(string $name): ?array
    {
        return $this->upserts[$name] ?? null;
    }

    public function all(): array
    {
        return $this->upserts;
    }
}
