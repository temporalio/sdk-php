<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker;

use Closure;
use React\Promise\PromiseInterface;
use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Internal\Events\EventListenerInterface;
use Temporal\Internal\Repository\Identifiable;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;

/**
 * Worker manages the execution of workflows and activities within the single TaskQueue. Activity and Workflow processing
 * will be launched using separate processes.
 */
class Worker implements WorkerInterface, Identifiable, EventListenerInterface, DispatcherInterface
{
    use EventEmitterTrait;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var WorkerOptions
     */
    private WorkerOptions $options;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var ServiceContainer
     */
    private ServiceContainer $services;

    /**
     * @var RPCConnectionInterface
     */
    private RPCConnectionInterface $rpc;

    /**
     * @param string $taskQueue
     * @param WorkerOptions $options
     * @param ServiceContainer $serviceContainer
     * @param RPCConnectionInterface $rpc
     */
    public function __construct(
        string $taskQueue,
        WorkerOptions $options,
        ServiceContainer $serviceContainer,
        RPCConnectionInterface $rpc
    ) {
        $this->rpc = $rpc;
        $this->name = $taskQueue;
        $this->options = $options;

        $this->services = $serviceContainer;
        $this->router = $this->createRouter();
    }

    /**
     * @return WorkerOptions
     */
    public function getOptions(): WorkerOptions
    {
        return $this->options;
    }

    /**
     * @param RequestInterface $request
     * @param array $headers
     * @return PromiseInterface
     */
    public function dispatch(RequestInterface $request, array $headers): PromiseInterface
    {
        return $this->router->dispatch($request, $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function getID(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function registerWorkflowTypes(string ...$class): WorkerInterface
    {
        foreach ($class as $workflow) {
            $proto = $this->services->workflowsReader->fromClass($workflow);
            $this->services->workflows->add($proto, false);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkflows(): RepositoryInterface
    {
        return $this->services->workflows;
    }

    /**
     * {@inheritDoc}
     */
    public function registerActivityImplementations(object ...$activity): WorkerInterface
    {
        foreach ($activity as $act) {
            $this->registerActivity(\get_class($act), fn() => $act);
        }

        return $this;
    }

    public function registerActivity(string $type, callable $factory = null): WorkerInterface
    {
        foreach ($this->services->activitiesReader->fromClass($type) as $proto) {
            if ($factory !== null) {
                $proto = $proto->withFactory($factory);
            }
            $this->services->activities->add($proto, false);
        }

        return $this;
    }

    public function registerActivityFinalizer(Closure $finalizer): WorkerInterface
    {
        $this->services->activities->addFinalizer($finalizer);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getActivities(): RepositoryInterface
    {
        return $this->services->activities;
    }

    /**
     * @return RouterInterface
     */
    protected function createRouter(): RouterInterface
    {
        $router = new Router();

        // Activity routes
        $router->add(new Router\InvokeActivity($this->services, $this->rpc));
        $router->add(new Router\InvokeLocalActivity($this->services, $this->rpc));

        // Workflow routes
        $router->add(new Router\StartWorkflow($this->services));
        $router->add(new Router\InvokeQuery($this->services->running, $this->services->loop));
        $router->add(new Router\InvokeSignal($this->services->running, $this->services->loop));
        $router->add(new Router\CancelWorkflow($this->services->running));
        $router->add(new Router\DestroyWorkflow($this->services->running));
        $router->add(new Router\StackTrace($this->services->running));

        return $router;
    }
}
