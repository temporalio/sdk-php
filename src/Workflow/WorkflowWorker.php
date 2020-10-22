<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use React\Promise\Deferred;
use Temporal\Client\Worker\Declaration\Repository\ActivityRepositoryInterface;
use Temporal\Client\Worker\Declaration\Repository\ActivityRepositoryTrait;
use Temporal\Client\Worker\Declaration\Repository\WorkflowRepositoryInterface;
use Temporal\Client\Worker\Declaration\Repository\WorkflowRepositoryTrait;
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Protocol\Queue\SplQueue;
use Temporal\Client\Worker\EmitterInterface;
use Temporal\Client\Workflow\Router\InvokeQueryMethod;
use Temporal\Client\Workflow\Router\InvokeSignalMethod;
use Temporal\Client\Workflow\Router\StartWorkflow;
use Temporal\Client\Workflow\Router;
use Temporal\Client\Workflow\RouterInterface;
use Temporal\Client\Worker\Uuid4;
use Temporal\Client\Workflow\Protocol\Context;
use Temporal\Client\Workflow\Protocol\WorkflowProtocol;
use Temporal\Client\Workflow\Protocol\WorkflowProtocolInterface;
use Temporal\Client\Workflow\Runtime\RunningWorkflows;

/**
 * @noinspection PhpSuperClassIncompatibleWithInterfaceInspection
 */
final class WorkflowWorker implements WorkflowRepositoryInterface, EmitterInterface
{
    use WorkflowRepositoryTrait;

    /**
     * @var string
     */
    private string $id;

    /**
     * @var WorkflowProtocolInterface
     */
    private WorkflowProtocolInterface $protocol;

    /**
     * @var RunningWorkflows
     */
    private RunningWorkflows $running;

    /**
     * @var QueueInterface
     */
    private QueueInterface $queue;

    /**
     * @var Context
     */
    private Context $context;

    /**
     * @var ReaderInterface
     */
    private ReaderInterface $reader;

    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    /**
     * @param ReaderInterface $reader
     * @param string $taskQueue
     * @throws \Exception
     */
    public function __construct(ReaderInterface $reader, string $taskQueue)
    {
        $this->bootWorkflowRepositoryTrait();

        $this->reader = $reader;
        $this->id = Uuid4::create();
        $this->queue = new SplQueue();
        $this->running = new RunningWorkflows();

        $this->router = new Router();

        $this->protocol = $this->createProtocol($this->router);
        $this->bootRoutes($this->router);
    }

    /**
     * @param RouterInterface $router
     * @return RouterInterface
     */
    private function bootRoutes(RouterInterface $router): RouterInterface
    {
        $router->add(new StartWorkflow($this->workflows, $this->running, $this->protocol));
        $router->add(new InvokeQueryMethod($this->workflows, $this->running));
        $router->add(new InvokeSignalMethod($this->workflows, $this->running));

        return $router;
    }

    /**
     * @return ReaderInterface
     */
    protected function getReader(): ReaderInterface
    {
        return $this->reader;
    }

    /**
     * @param RouterInterface $router
     * @return WorkflowProtocolInterface
     * @throws \Exception
     */
    private function createProtocol(RouterInterface $router): WorkflowProtocolInterface
    {
        $handler = function (RequestInterface $request, Deferred $deferred) use ($router): void {
            $router->emit($request, $deferred);
        };

        return new WorkflowProtocol($this->queue, $handler);
    }

    /**
     * @param string $request
     * @param array $context
     * @return string
     */
    public function emit(string $request, array $context = []): string
    {
        return $this->protocol->next($request);
    }
}
