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
use Nexus\Sdk\Handler\ServiceImplInstance;
use Temporal\Client\WorkflowClient;
use Temporal\Internal\Declaration\EntityNameValidator;
use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Internal\Events\EventListenerInterface;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\Nexus\PayloadSerializer;
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

    /**
     * Optional client wired in by {@see \Temporal\WorkerFactory} when one was
     * provided to `WorkerFactory::create()`. Used to build the
     * {@see \Temporal\Nexus\NexusOperationContext} that Nexus operation handlers
     * (notably `WorkflowRunOperation`) need to start workflows.
     */
    private ?WorkflowClient $workflowClient = null;

    public function __construct(
        string $taskQueue,
        WorkerOptions $options,
        ServiceContainer $serviceContainer,
        RPCConnectionInterface $rpc,
        ?WorkflowClient $workflowClient = null,
    ) {
        EntityNameValidator::validateTaskQueue($taskQueue);

        $this->rpc = $rpc;
        $this->name = $taskQueue;
        $this->options = $options;
        $this->workflowClient = $workflowClient;

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
        $seenInCall = [];
        foreach ($services as $index => $service) {
            $instance = ServiceImplInstance::fromInstance($service);
            $name = $instance->definition->name;

            if (isset($seenInCall[$name])) {
                throw new \InvalidArgumentException(\sprintf(
                    'registerNexusServiceImplementation: services at positions %d and %d '
                    . 'both declare Nexus service "%s"',
                    $seenInCall[$name],
                    $index,
                    $name,
                ));
            }
            $seenInCall[$name] = $index;

            $this->services->nexusServices->add($instance);
        }

        return $this;
    }

    public function getNexusServices(): array
    {
        $result = [];
        foreach ($this->services->nexusServices->getInstances() as $instance) {
            $result[] = [
                'name' => $instance->definition->name,
                'operations' => \array_keys($instance->definition->operations),
            ];
        }
        return $result;
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
        $nexusHandler = $this->createNexusTaskHandler();
        $invocations = $this->services->nexusInvocations;
        $router->add(new Router\InvokeNexusOperation($nexusHandler, $invocations));
        $router->add(new Router\CancelNexusOperation($nexusHandler));
        $router->add(new Router\CancelNexusOperationMethod($invocations));

        return $router;
    }

    private function createNexusTaskHandler(): NexusTaskHandler
    {
        $handler = new NexusTaskHandler(
            $this->services->nexusServices,
            new PayloadSerializer($this->services->dataConverter),
            $this->services->dataConverter,
        );

        // Hand the worker's task queue + the factory-supplied client to the
        // task handler so it can build a NexusOperationContext for every
        // dispatch. Workers without a client (e.g. activity-only setups that
        // never registered a Nexus service) skip this — handlers that need
        // the context will surface a clear error via Nexus::getOperationContext().
        if ($this->workflowClient !== null) {
            $handler->withWorkerEnvironment(
                $this->workflowClient->getClientOptions()->namespace,
                $this->name,
                $this->workflowClient,
            );
        }

        return $handler;
    }
}
