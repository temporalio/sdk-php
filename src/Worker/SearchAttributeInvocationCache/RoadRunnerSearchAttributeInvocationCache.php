<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\SearchAttributeInvocationCache;

use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;

final class RoadRunnerSearchAttributeInvocationCache implements SearchAttributeInvocationCacheInterface
{
    private const CACHE_NAME = 'test';
    private const STORE_KEY = '__temporal_testing_search_attributes__';

    private StorageInterface $cache;

    /**
     * @param non-empty-string $host
     * @param non-empty-string $cacheName
     */
    public function __construct(string $host, string $cacheName)
    {
        $this->cache = (new Factory(RPC::create($host)))->select($cacheName);
    }

    public static function create(): self
    {
        $env = Environment::fromGlobals();
        $host = $env->getRPCAddress();
        if ($host === '') {
            throw new \RuntimeException('RoadRunner RPC address is not set.');
        }

        return new self($host, self::CACHE_NAME);
    }

    public function clear(): void
    {
        $this->cache->delete(self::STORE_KEY);
    }

    public function recordUpsert(string $name, array $entry): void
    {
        $map = $this->map();
        $map[$name] = $entry;
        $this->cache->set(self::STORE_KEY, $map);
    }

    public function wasUpserted(string $name): bool
    {
        return \array_key_exists($name, $this->map());
    }

    public function getUpsert(string $name): ?array
    {
        return $this->map()[$name] ?? null;
    }

    public function all(): array
    {
        return $this->map();
    }

    /**
     * @return array<string, array{operation: string, type: string, value?: mixed}>
     */
    private function map(): array
    {
        $map = $this->cache->get(self::STORE_KEY);

        return \is_array($map) ? $map : [];
    }
}
