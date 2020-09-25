<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Declaration\Activity;
use Temporal\Client\Declaration\ActivityInterface;
use Temporal\Client\Declaration\Collection;
use Temporal\Client\Declaration\CollectionInterface;
use Temporal\Client\Declaration\Workflow;
use Temporal\Client\Declaration\WorkflowInterface;
use Temporal\Client\Meta\Factory as MetadataFactory;
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Protocol\JsonRpcProtocol;
use Temporal\Client\Protocol\Message\Request;
use Temporal\Client\Protocol\Message\RequestInterface;
use Temporal\Client\Protocol\Transport\TransportInterface;
use Temporal\Client\Runtime\Route\StartActivity;
use Temporal\Client\Runtime\Route\StartWorkflow;
use Temporal\Client\Runtime\Router;
use Temporal\Client\Runtime\RouterInterface;

class Worker implements MutableWorkerInterface
{
    /**
     * @psalm-var CollectionInterface<WorkflowInterface>
     *
     * @var CollectionInterface|WorkflowInterface[]
     */
    private CollectionInterface $workflows;

    /**
     * @psalm-var CollectionInterface<ActivityInterface>
     *
     * @var CollectionInterface|ActivityInterface[]
     */
    private CollectionInterface $activities;

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var array|\Closure[]
     */
    private array $errorHandlers = [];

    /**
     * @param TransportInterface $transport
     * @param LoopInterface $loop
     * @throws \Exception
     */
    public function __construct(TransportInterface $transport, LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->activities = new Collection();
        $this->workflows = new Collection();
        $this->reader = $this->createReader();

        $this->client = $this->createClient($transport);
        $this->router = new Router($this->client);

        $this->boot();
    }

    /**
     * @return ReaderInterface
     */
    protected function createReader(): ReaderInterface
    {
        return (new MetadataFactory())->create();
    }

    /**
     * @param TransportInterface $transport
     * @return ClientInterface
     */
    protected function createClient(TransportInterface $transport): ClientInterface
    {
        return new JsonRpcProtocol($transport, function (RequestInterface $request, Deferred $resolver): void {
            $this->router->emit($request, $resolver);
        });
    }

    /**
     * @return void
     */
    private function boot(): void
    {
        $this->registerDefaultRoutes();
    }

    /**
     * @return void
     */
    private function registerDefaultRoutes(): void
    {
        $this->router->add(new StartWorkflow($this->workflows, $this->client));
        $this->router->add(new StartActivity($this->activities, $this->client));
    }

    /**
     * @param \Closure $then
     */
    public function onError(\Closure $then): void
    {
        $this->errorHandlers[] = $then;
    }

    /**
     * {@inheritDoc}
     */
    public function addActivity(object $activity, bool $overwrite = false): void
    {
        $activities = Activity::fromObject($activity, $this->reader);

        foreach ($activities as $declaration) {
            $this->addActivityDeclaration($declaration, $overwrite);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addActivityDeclaration(ActivityInterface $activity, bool $overwrite = false): void
    {
        $this->activities->add($activity, $overwrite);
    }

    /**
     * {@inheritDoc}
     */
    public function addWorkflow(object $workflow, bool $overwrite = false): void
    {
        $workflows = Workflow::fromObject($workflow, $this->reader);

        foreach ($workflows as $declaration) {
            $this->addWorkflowDeclaration($declaration, $overwrite);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addWorkflowDeclaration(WorkflowInterface $workflow, bool $overwrite = false): void
    {
        $this->workflows->add($workflow, $overwrite);
    }

    /**
     * @param string $name
     * @return WorkflowInterface|null
     */
    public function findWorkflow(string $name): ?WorkflowInterface
    {
        return $this->workflows->find($name);
    }

    /**
     * @param string $name
     * @return ActivityInterface|null
     */
    public function findActivity(string $name): ?ActivityInterface
    {
        return $this->activities->find($name);
    }

    /**
     * @return ClientInterface
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    /**
     * @return RouterInterface
     */
    public function getRouter(): RouterInterface
    {
        return $this->router;
    }

    /**
     * @param string $name
     * @return int
     * @throws \Throwable
     */
    public function run(string $name = self::DEFAULT_WORKER_ID): int
    {
        try {
            $this->handshake($name);

            $this->loop->run();
        } catch (\Throwable $e) {
            $this->emitError($e);

            try {
                return $e->getCode() ?: -1;
            } finally {
                throw $e;
            }
        }

        return 0;
    }

    /**
     * @param \Throwable $e
     * @param int $depth
     */
    private function emitError(\Throwable $e, int $depth = 0): void
    {
        // TODO configure recursive errors depth
        if ($depth > 10) {
            return;
        }

        foreach ($this->errorHandlers as $handler) {
            try {
                $handler($e);
            } catch (\Throwable $e) {
                $this->emitError($e);
            }
        }
    }

    /**
     * @param string $name
     */
    public function handshake(string $name = self::DEFAULT_WORKER_ID): void
    {
        $workflows = $activities = [];

        foreach ($this->activities as $options => $activity) {
            $activities[] = \array_merge($options, [
                'name' => $activity->getName(),
            ]);
        }

        foreach ($this->workflows as $options => $workflow) {
            $workflows[] = \array_merge($options, [
                'name' => $workflow->getName(),
            ]);
        }

        $this->call('CreateWorker', [
            'taskQueue'  => $name,
            'activities' => $activities,
            'workflows'  => $workflows,
        ])
            ->then(fn() => $this->call('StartWorker'))
        ;
    }

    /**
     * @param string $method
     * @param array $params
     * @return PromiseInterface
     */
    private function call(string $method, array $params = []): PromiseInterface
    {
        return $this->client->request(new Request($method, $params));
    }
}
