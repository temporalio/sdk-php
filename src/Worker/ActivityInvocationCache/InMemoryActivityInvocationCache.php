<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

use function React\Promise\reject;
use function React\Promise\resolve;

final class InMemoryActivityInvocationCache implements ActivityInvocationCacheInterface
{
    /**
     * @var array<non-empty-string, ActivityInvocationFailure|ActivityInvocationResult>
     */
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

    public function saveCompletion(string $activityMethodName, mixed $value): void
    {
        $this->cache[$activityMethodName] = ActivityInvocationResult::fromValue($value, $this->dataConverter);
    }

    public function saveFailure(string $activityMethodName, \Throwable $error): void
    {
        $this->cache[$activityMethodName] = ActivityInvocationFailure::fromThrowable($error);
    }

    public function canHandle(ServerRequestInterface $request): bool
    {
        if ($request->getName() !== 'InvokeActivity') {
            return false;
        }

        $activityMethodName = $request->getOptions()['name'] ?? '';

        return isset($this->cache[$activityMethodName]);
    }

    public function execute(ServerRequestInterface $request): PromiseInterface
    {
        $activityMethodName = $request->getOptions()['name'];
        $value = $this->cache[$activityMethodName];

        return $value instanceof ActivityInvocationFailure
            ? reject($value->toThrowable())
            : resolve($value->toEncodedValues($this->dataConverter));
    }
}
