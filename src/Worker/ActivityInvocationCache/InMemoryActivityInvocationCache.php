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

final class InMemoryActivityInvocationCache implements ActivityInvocationCacheInterface
{
    private array $cache = [];

    public function clear(): void
    {
        $this->cache = [];
    }

    public function saveCompletion(string $activityMethodName, $value): void
    {
        $this->cache[$activityMethodName] = $value;
    }

    public function saveFailure(string $activityMethodName, Throwable $error): void
    {
        $this->cache[$activityMethodName] = ActivityInvocationFailure::fromThrowable($error);
    }

    public function canHandle(RequestInterface $request): bool
    {
        if ($request->getName() !== 'InvokeActivity') {
            return false;
        }

        $activityMethodName = $request->getOptions()['name'] ?? '';

        return isset($this->cache[$activityMethodName]);
    }

    public function execute(RequestInterface $request): PromiseInterface
    {
        $activityMethodName = $request->getOptions()['name'];
        $value = $this->cache[$activityMethodName];

        if ($value instanceof ActivityInvocationFailure) {
            return reject($value->toThrowable());
        }

        return resolve(EncodedValues::fromValues([$value]));
    }
}
