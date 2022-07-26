<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use React\Promise\PromiseInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Worker\Transport\Command\RequestInterface;
use Throwable;

use function React\Promise\reject;
use function React\Promise\resolve;

final class RoadRunnerActivityInvocationCache implements ActivityInvocationCacheInterface
{
    private const CACHE_NAME = 'test';
    private StorageInterface $cache;

    public function __construct(string $host, string $cacheName)
    {
        $this->cache = (new Factory(RPC::create($host)))->select($cacheName);
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    public static function create(): self
    {
        return new self('tcp://127.0.0.1:6001', self::CACHE_NAME);
    }

    public function saveCompletion(string $activityMethodName, $value): void
    {
        $this->cache->set($activityMethodName, $value);
    }

    public function saveFailure(string $activityMethodName, Throwable $error): void
    {
        $this->cache->set($activityMethodName, ActivityInvocationFailure::fromThrowable($error));
    }

    public function canHandle(RequestInterface $request): bool
    {
        if ($request->getName() !== 'InvokeActivity') {
            return false;
        }

        $activityMethodName = $request->getOptions()['name'] ?? '';

        return $this->cache->has($activityMethodName);
    }

    public function execute(RequestInterface $request): PromiseInterface
    {
        $activityMethodName = $request->getOptions()['name'];
        $value = $this->cache->get($activityMethodName);

        if ($value instanceof ActivityInvocationFailure) {
            return reject($value->toThrowable());
        }

        return resolve(EncodedValues::fromValues([$value]));
    }
}
