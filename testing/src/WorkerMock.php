<?php

declare(strict_types=1);

namespace Temporal\Testing;

use React\Promise\PromiseInterface;
use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Internal\Events\EventListenerInterface;
use Temporal\Worker\ActivityInvocationCache\ActivityInvocationCacheInterface;
use Temporal\Worker\DispatcherInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

/**
 * @template-implements EventListenerInterface<string>
 */
final class WorkerMock implements WorkerInterface, EventListenerInterface, DispatcherInterface
{
    /** @use EventEmitterTrait<string> */
    use EventEmitterTrait;

    public function __construct(
        private WorkerInterface&DispatcherInterface $wrapped,
        private ActivityInvocationCacheInterface $activityInvocationCache,
    ) {}

    public function getOptions(): WorkerOptions
    {
        return $this->wrapped->getOptions();
    }

    public function dispatch(ServerRequestInterface $request, array $headers): PromiseInterface
    {
        if ($this->activityInvocationCache->canHandle($request)) {
            return $this->activityInvocationCache->execute($request);
        }

        return $this->wrapped->dispatch($request, $headers);
    }

    public function getID(): int|string
    {
        return $this->wrapped->getID();
    }

    public function registerWorkflowTypes(string ...$class): WorkerInterface
    {
        $this->wrapped->registerWorkflowTypes(...$class);

        return $this;
    }

    public function getWorkflows(): iterable
    {
        return $this->wrapped->getWorkflows();
    }

    public function registerActivityImplementations(object ...$activity): WorkerInterface
    {
        $this->wrapped->registerActivityImplementations(...$activity);

        return $this;
    }

    public function registerActivity(string $type, ?callable $factory = null): WorkerInterface
    {
        $this->wrapped->registerActivity($type, $factory);

        return $this;
    }

    public function registerActivityFinalizer(\Closure $finalizer): WorkerInterface
    {
        $this->wrapped->registerActivityFinalizer($finalizer);

        return $this;
    }

    public function getActivities(): iterable
    {
        return $this->wrapped->getActivities();
    }
}
