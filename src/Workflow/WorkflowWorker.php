<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Command\ResponseInterface;
use Temporal\Client\Protocol\DispatcherInterface;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Protocol\Queue\SplQueue;
use Temporal\Client\Protocol\Router;
use Temporal\Client\Protocol\RouterInterface;
use Temporal\Client\Worker\Declaration\Repository\WorkflowRepositoryInterface;
use Temporal\Client\Worker\Declaration\Repository\WorkflowRepositoryTrait;
use Temporal\Client\Worker\Uuid4;
use Temporal\Client\Workflow\Runtime\RunningWorkflows;

/**
 * @noinspection PhpSuperClassIncompatibleWithInterfaceInspection
 */

final class WorkflowWorker implements WorkflowRepositoryInterface, DispatcherInterface
{
    use WorkflowRepositoryTrait;

    /**
     * @var string
     */
    private string $id;

    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $running;

    /**
     * @var QueueInterface
     */
    private QueueInterface $queue;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @param ClientInterface $client
     * @param ReaderInterface $reader
     * @param string $taskQueue
     * @throws \Exception
     */
    public function __construct(ClientInterface $client, ReaderInterface $reader, string $taskQueue)
    {
        $this->reader = $reader;

        $this->id = Uuid4::create();
        $this->queue = new SplQueue();

        $this->bootWorkflowRepositoryTrait();

        $this->running = new RunningWorkflows();

        $this->router = new Router();
        $this->router->add(new Router\StartWorkflow($this->workflows, $this->running, $client));
        $this->router->add(new Router\InvokeQueryMethod($this->workflows, $this->running));
        $this->router->add(new Router\InvokeSignalMethod($this->workflows, $this->running));
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(RequestInterface $request, array $headers = []): ResponseInterface
    {
        return $this->router->dispatch($request, $headers);
    }

    /**
     * @return ReaderInterface
     */
    protected function getReader(): ReaderInterface
    {
        return $this->reader;
    }
}
