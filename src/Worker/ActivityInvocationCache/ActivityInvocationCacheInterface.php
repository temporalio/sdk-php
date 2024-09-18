<?php

declare(strict_types=1);

namespace Temporal\Worker\ActivityInvocationCache;

use React\Promise\PromiseInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;

interface ActivityInvocationCacheInterface
{
    public function clear(): void;

    /**
     * @param non-empty-string $activityMethodName
     */
    public function saveCompletion(string $activityMethodName, mixed $value): void;

    /**
     * @param non-empty-string $activityMethodName
     */
    public function saveFailure(string $activityMethodName, \Throwable $error): void;

    public function canHandle(ServerRequestInterface $request): bool;

    public function execute(ServerRequestInterface $request): PromiseInterface;
}
