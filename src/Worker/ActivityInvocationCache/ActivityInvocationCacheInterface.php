<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use React\Promise\PromiseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Throwable;

interface ActivityInvocationCacheInterface
{
    public function clear(): void;

    public function saveCompletion(string $activityMethodName, $value): void;

    public function saveFailure(string $activityMethodName, Throwable $error): void;

    public function canHandle(RequestInterface $request): bool;

    public function execute(RequestInterface $request): PromiseInterface;
}
