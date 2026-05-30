<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use React\Promise\PromiseInterface;
use Temporal\Internal\Declaration\EntityNameValidator;
use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Internal\Events\EventListenerInterface;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\Worker\Transport\Command\ServerRequestInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;

/**
 * Worker manages the execution of workflows and activities within the single TaskQueue. Activity and Workflow processing
 * will be launched using separate processes.
 */
class Worker implements WorkerInterface, EventListenerInterface, DispatcherInterface
{
    use EventEmitterTrait;

    private string $name;
    private WorkerOptions $options;
    private RouterInterface $router;
    private ServiceContainer $services;
    private RPCConnectionInterface $rpc;

    public function __construct(
        string $taskQueue,
        WorkerOptions $options,
        ServiceContainer $serviceContainer,
        RPCConnectionInterface $rpc,
    ) {
        EntityNameValidator::validateTaskQueue($taskQueue);

        $this->rpc = $rpc;
        $this->name = $taskQueue;
        $this->options = $options;

        $this->services = $serviceContainer;
        $this->router = $this->createRouter();
    }

    public function getOptions(): WorkerOptions
    {
        return $this->options;
    }

    public function dispatch(ServerRequestInterface $request, array $headers): PromiseInterface
    {
        return $this->router->dispatch($request, $headers);
    }

    public function getID(): string
    {
        return $this->name;
    }

    public function registerWorkflowTypes(string ...$class): WorkerInterface
    {
        foreach ($class as $workflow) {
            $proto = $this->services->workflowsReader->fromClass($workflow);
            $this->services->workflows->add($proto, false);
        }

        return $this;
    }

    public function getWorkflows(): RepositoryInterface
    {
        return $this->services->workflows;
    }

    public function registerActivityImplementations(object ...$activity): WorkerInterface
    {
        foreach ($activity as $act) {
            $this->registerActivity(\get_class($act), static fn() => $act);
        }

        return $this;
    }

    public function registerActivity(string $type, ?callable $factory = null): WorkerInterface
    {
        foreach ($this->services->activitiesReader->fromClass($type) as $proto) {
            if ($factory !== null) {
                $proto = $proto->withFactory($factory instanceof \Closure ? $factory : \Closure::fromCallable($factory));
            }
            $this->services->activities->add($proto, false);
        }

        return $this;
    }

    public function registerActivityFinalizer(\Closure $finalizer): WorkerInterface
    {
        $this->services->activities->addFinalizer($finalizer);

        return $this;
    }

    public function getActivities(): RepositoryInterface
    {
        return $this->services->activities;
    }

    public function registerNexusServiceImplementation(object ...$services): WorkerInterface
    {
        if ($this->services->nexusEnvironment === null && $services !== []) {
            throw new \LogicException(
                'Cannot register Nexus service implementations on a worker without a WorkflowClient. ' .
                'Pass a WorkflowClient to the WorkerFactory (e.g. WorkerFactory::create(client: $workflowClient)) ' .
                '— Nexus operations require cluster access.',
            );
        }

        foreach ($services as $service) {
            $prototype = $this->services->nexusServicesReader->fromClass(\get_class($service));
            $this->services->nexusServices->add($prototype->withInstance($service), false);
        }

        return $this;
    }

    public function getNexusServices(): RepositoryInterface
    {
        return $this->services->nexusServices;
    }

    protected function createRouter(): RouterInterface
    {
        $router = new Router();

        // Activity routes
        $router->add(new Router\InvokeActivity($this->services, $this->rpc, $this->services->interceptorProvider));
        $router->add(new Router\InvokeLocalActivity($this->services, $this->rpc, $this->services->interceptorProvider));

        // Workflow routes
        $router->add(new Router\StartWorkflow($this->services));
        $router->add(new Router\InvokeQuery($this->services->running, $this->services->loop));
        $router->add(new Router\InvokeSignal($this->services->running));
        $router->add(new Router\InvokeUpdate($this->services->running));
        $router->add(new Router\CancelWorkflow($this->services->running));
        $router->add(new Router\DestroyWorkflow($this->services->running, $this->services->loop));
        $router->add(new Router\StackTrace($this->services->running));

        // Nexus routes
        $router->add(new Router\InvokeNexusOperation($this->services->nexusTaskHandler, $this->services->nexusInvocations, $this->services->dataConverter));
        $router->add(new Router\CancelNexusOperation($this->services->nexusTaskHandler));
        $router->add(new Router\CancelNexusOperationMethod($this->services->nexusInvocations));

        return $router;
    }
}
