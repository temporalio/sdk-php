<?php

declare(strict_types=1);

namespace Temporal\Testing;

use Closure;
use React\Promise\PromiseInterface;
use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Internal\Events\EventListenerInterface;
use Temporal\Internal\Repository\Identifiable;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Worker\ActivityInvocationCache\ActivityInvocationCacheInterface;
use Temporal\Worker\DispatcherInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

final class WorkerMock implements WorkerInterface, Identifiable, EventListenerInterface, DispatcherInterface
{
    use EventEmitterTrait;

    private WorkerInterface $wrapped;
    private ActivityInvocationCacheInterface $activityInvocationCache;

    public function __construct(
        WorkerInterface $wrapped,
        ActivityInvocationCacheInterface $activityInvocationCache
    ) {
        $this->wrapped = $wrapped;
        $this->activityInvocationCache = $activityInvocationCache;
    }

    public function getOptions(): WorkerOptions
    {
        return $this->wrapped->getOptions();
    }

    public function dispatch(RequestInterface $request, array $headers): PromiseInterface
    {
        if ($this->activityInvocationCache->canHandle($request)) {
            return $this->activityInvocationCache->execute($request);
        }

        return $this->wrapped->dispatch($request, $headers);
    }

    public function getID(): string
    {
        return $this->wrapped->getID();
    }

    public function registerWorkflowTypes(string ...$class): WorkerInterface
    {
        return $this->wrapped->registerWorkflowTypes(...$class);
    }

    public function getWorkflows(): RepositoryInterface
    {
        return $this->wrapped->getWorkflows();
    }

    public function registerActivityImplementations(object ...$activity): WorkerInterface
    {
        return $this->wrapped->registerActivityImplementations(...$activity);
    }

    public function registerActivity(string $type, callable $factory = null): WorkerInterface
    {
        return $this->wrapped->registerActivity($type, $factory);
    }

    public function registerActivityFinalizer(Closure $finalizer): WorkerInterface
    {
        return $this->wrapped->registerActivityFinalizer($finalizer);
    }

    public function getActivities(): RepositoryInterface
    {
        return $this->wrapped->getActivities();
    }
}
