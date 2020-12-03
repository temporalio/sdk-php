<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use React\Promise\PromiseInterface;
use Spiral\Attributes\ReaderInterface;
use Temporal\Client\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Client\Internal\Declaration\Prototype\Collection;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Declaration\Reader\ActivityReader;
use Temporal\Client\Internal\Declaration\Reader\WorkflowReader;
use Temporal\Client\Internal\Events\EventEmitterTrait;
use Temporal\Client\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Client\Internal\Marshaller\Marshaller;
use Temporal\Client\Internal\Marshaller\MarshallerInterface;
use Temporal\Client\Internal\Repository\ArrayRepository;
use Temporal\Client\Internal\Repository\RepositoryInterface;
use Temporal\Client\Internal\Transport\ClientInterface;
use Temporal\Client\Internal\Transport\Router;
use Temporal\Client\Internal\Transport\RouterInterface;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Worker\Environment\Environment;
use Temporal\Client\Worker\Environment\EnvironmentInterface;
use Temporal\Client\Workflow\ProcessInterface;

class TaskQueue implements TaskQueueInterface
{
    use EventEmitterTrait;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var MarshallerInterface
     */
    private MarshallerInterface $marshaller;

    /**
     * @var Collection<WorkflowPrototype>
     */
    private Collection $workflows;

    /**
     * @var WorkflowReader
     */
    private WorkflowReader $workflowReader;

    /**
     * @var Collection<ActivityPrototype>
     */
    private Collection $activities;

    /**
     * @var ActivityReader
     */
    private ActivityReader $activityReader;

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var EnvironmentInterface
     */
    private EnvironmentInterface $env;

    /**
     * @var RepositoryInterface<ProcessInterface>
     */
    private RepositoryInterface $processes;

    /**
     * @param string $name
     * @param ReaderInterface $reader
     */
    public function __construct(string $name, ReaderInterface $reader, ClientInterface $client)
    {
        $this->name = $name;
        $this->reader = $reader;
        $this->client = $client;

        $this->boot();
    }

    /**
     * @return void
     */
    private function boot(): void
    {
        $this->env = $this->createEnvironment();
        $this->marshaller = $this->createMarshaller();

        $this->workflows = new Collection();
        $this->activities = new Collection();
        $this->processes = new ArrayRepository();

        $this->workflowReader = new WorkflowReader($this->reader);
        $this->activityReader = new ActivityReader($this->reader);

        $this->router = $this->createRouter();
    }

    /**
     * @return EnvironmentInterface
     */
    protected function createEnvironment(): EnvironmentInterface
    {
        return new Environment();
    }

    /**
     * @return MarshallerInterface
     */
    protected function createMarshaller(): MarshallerInterface
    {
        $factory = new AttributeMapperFactory($this->reader);

        return new Marshaller($factory);
    }

    /**
     * @return RouterInterface
     */
    protected function createRouter(): RouterInterface
    {
        $router = new Router();

        // Activity routes
        $router->add(new Router\InvokeActivity($this->marshaller, $this->activities));

        // Workflow routes
        $router->add(new Router\StartWorkflow($this->workflows, $this->processes, $this));
        // $router->add(new Router\InvokeQuery($this->workflows));
        // $router->add(new Router\InvokeSignal($this->workflows, $worker));
        // $router->add(new Router\DestroyWorkflow($this->workflows, $worker));
        // $router->add(new Router\StackTrace($this->workflows));

        return $router;
    }

    /**
     * @param RequestInterface $request
     * @param array $headers
     * @return PromiseInterface
     */
    public function dispatch(RequestInterface $request, array $headers): PromiseInterface
    {
        $this->env->update($headers);

        return $this->router->dispatch($request, $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function getId(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function addWorkflow(string $class, bool $overwrite = false): TaskQueueInterface
    {
        foreach ($this->workflowReader->fromClass($class) as $workflow) {
            $this->workflows->add($workflow, $overwrite);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getWorkflows(): iterable
    {
        return $this->workflows;
    }

    /**
     * {@inheritDoc}
     */
    public function addActivity(string $class, bool $overwrite = false): TaskQueueInterface
    {
        foreach ($this->activityReader->fromClass($class) as $activity) {
            $this->activities->add($activity, $overwrite);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getActivities(): iterable
    {
        return $this->activities;
    }
}
