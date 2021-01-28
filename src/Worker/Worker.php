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
use Temporal\Internal\Events\EventEmitterTrait;
use Temporal\Internal\Events\EventListenerInterface;
use Temporal\Internal\Repository\Identifiable;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\WorkerFactory;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;

class Worker implements WorkerInterface, Identifiable, EventListenerInterface, DispatcherInterface
{
    use EventEmitterTrait;

    /**
     * @var string
     */
    private string $name;

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
     * @param WorkerFactory $worker
     * @param RPCConnectionInterface $rpc
     */
    public function __construct(string $taskQueue, WorkerFactory $worker, RPCConnectionInterface $rpc)
    {
        $this->rpc = $rpc;
        $this->name = $taskQueue;

        $this->services = ServiceContainer::fromWorker($worker);
        $this->router = $this->createRouter();
    }

    /**
     * @return RouterInterface
     */
    protected function createRouter(): RouterInterface
    {
        $router = new Router();

        // Activity routes
        $router->add(new Router\InvokeActivity($this->services, $this->rpc));

        // Workflow routes
        $router->add(new Router\StartWorkflow($this->services));
        $router->add(new Router\InvokeQuery($this->services->running, $this->services->loop));
        $router->add(new Router\InvokeSignal($this->services->running, $this->services->loop));
        $router->add(new Router\CancelWorkflow($this->services->running, $this->services->client));
        $router->add(new Router\DestroyWorkflow($this->services->running, $this->services->client));
        $router->add(new Router\StackTrace($this->services->running));

        return $router;
    }

    /**
     * @param RequestInterface $request
     * @param array $headers
     * @return PromiseInterface
     */
    public function dispatch(RequestInterface $request, array $headers): PromiseInterface
    {
        $this->services->env->update($headers);

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
    public function registerWorkflowType(string $class, bool $overwrite = false): WorkerInterface
    {
        $proto = $this->services->workflowsReader->fromClass($class);

        $this->services->workflows->add($proto, $overwrite);

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
    public function registerActivityType(string $class, bool $overwrite = false): WorkerInterface
    {
        foreach ($this->services->activitiesReader->fromClass($class) as $proto) {
            $this->services->activities->add($proto, $overwrite);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function registerActivityImplementation(object $activity, bool $overwrite = false): WorkerInterface
    {
        $class = \get_class($activity);

        foreach ($this->services->activitiesReader->fromClass($class) as $proto) {
            $proto->setInstance($activity);

            $this->services->activities->add($proto, $overwrite);
        }

        return $this;
    }

    // todo: add activity factory or container

    /**
     * {@inheritDoc}
     */
    public function getActivities(): RepositoryInterface
    {
        return $this->services->activities;
    }
}
