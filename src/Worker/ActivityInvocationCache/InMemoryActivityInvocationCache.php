<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use React\Promise\PromiseInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\EncodedValues;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Worker\Transport\Command\RequestInterface;
use Throwable;

use function React\Promise\reject;
use function React\Promise\resolve;

final class InMemoryActivityInvocationCache implements ActivityInvocationCacheInterface
{
    private array $cache = [];
    private DataConverterInterface $dataConverter;

    public function __construct(DataConverterInterface $dataConverter = null)
    {
        $this->dataConverter = $dataConverter ?? DataConverter::createDefault();
    }

    public function clear(): void
    {
        $this->cache = [];
    }

    public function saveCompletion(string $activityMethodName, $value): void
    {
        $this->cache[$activityMethodName] = ActivityInvocationResult::fromValue($value, $this->dataConverter);
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

        if ($value instanceof ActivityInvocationResult) {
            return resolve($value->toEncodedValues($this->dataConverter));
        }

        return reject(new InvalidArgumentException('Invalid cache value'));
    }
}
