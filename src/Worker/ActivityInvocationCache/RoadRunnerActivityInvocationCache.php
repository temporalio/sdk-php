<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use React\Promise\PromiseInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Worker\Transport\Command\RequestInterface;
use Throwable;
use function React\Promise\reject;
use function React\Promise\resolve;

final class RoadRunnerActivityInvocationCache implements ActivityInvocationCacheInterface
{
    private const CACHE_NAME = 'test';
    private StorageInterface $cache;
    private DataConverterInterface $dataConverter;

    public function __construct(string $host, string $cacheName, DataConverterInterface $dataConverter = null)
    {
        $this->cache = (new Factory(RPC::create($host)))->select($cacheName);
        $this->dataConverter = $dataConverter ?? DataConverter::createDefault();
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    public static function create(DataConverterInterface $dataConverter = null): self {
        return new self('tcp://127.0.0.1:6001', self::CACHE_NAME, $dataConverter);
    }

    public function saveCompletion(string $activityMethodName, $value): void
    {
        $this->cache->set($activityMethodName, ActivityInvocationResult::fromValue($value, $this->dataConverter));
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

        if ($value instanceof ActivityInvocationResult) {
            return resolve($value->toEncodedValues($this->dataConverter));
        }

        return reject(new InvalidArgumentException('Invalid cache value'));
    }
}
