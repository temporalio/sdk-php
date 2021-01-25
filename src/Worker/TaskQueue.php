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
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Router;
use Temporal\Internal\Transport\RouterInterface;
use Temporal\Worker;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\Transport\RPCConnectionInterface;

class TaskQueue implements TaskQueueInterface
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
     * @param string $name
     * @param Worker $worker
     * @param RPCConnectionInterface $rpc
     */
    public function __construct(string $name, Worker $worker, RPCConnectionInterface $rpc)
    {
        $this->rpc = $rpc;
        $this->name = $name;

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
    public function addWorkflow(string $class, bool $overwrite = false): TaskQueueInterface
    {
        $workflow = $this->services->workflowsReader->fromClass($class);

        $this->services->workflows->add($workflow, $overwrite);

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
    public function addActivity(string $class, bool $overwrite = false): TaskQueueInterface
    {
        foreach ($this->services->activitiesReader->fromClass($class) as $activity) {
            $this->services->activities->add($activity, $overwrite);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getActivities(): RepositoryInterface
    {
        return $this->services->activities;
    }
}
