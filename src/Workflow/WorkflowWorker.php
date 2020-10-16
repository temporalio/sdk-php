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
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\Queue\QueueInterface;
use Temporal\Client\Protocol\Queue\SplQueue;
use Temporal\Client\Protocol\Transport\TransportInterface;
use Temporal\Client\Worker\Route\GetWorkerInfo;
use Temporal\Client\Worker\Route\InvokeQueryMethod;
use Temporal\Client\Worker\Route\InvokeSignalMethod;
use Temporal\Client\Worker\Route\StartActivity;
use Temporal\Client\Worker\Route\StartWorkflow;
use Temporal\Client\Worker\RouterInterface;
use Temporal\Client\Worker\Uuid4;
use Temporal\Client\Worker\Worker;
use Temporal\Client\Workflow\Protocol\WorkflowProtocol;
use Temporal\Client\Workflow\Protocol\WorkflowProtocolInterface;
use Temporal\Client\Workflow\Runtime\RunningWorkflows;

class WorkflowWorker extends Worker implements WorkflowWorkerInterface
{
    use WorkflowProviderTrait;

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
     * @param ReaderInterface $reader
     * @param TransportInterface $transport
     * @throws \Exception
     */
    public function __construct(ReaderInterface $reader, TransportInterface $transport)
    {
        parent::__construct($reader, $transport);

        $this->id = Uuid4::create();
        $this->queue = new SplQueue();
        $this->protocol = $this->createProtocol($this->router);
        $this->running = new RunningWorkflows();

        $this->bootWorkflowProviderTrait();
        $this->bootGlobalRoutes();
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
     * @return void
     */
    private function bootGlobalRoutes(): void
    {
        $this->router->add(new StartWorkflow($this->workflows, $this->running, $this->protocol));
        $this->router->add(new InvokeQueryMethod($this->workflows, $this->running));
        $this->router->add(new InvokeSignalMethod($this->workflows, $this->running));
    }

    /**
     * @param string $name
     * @return int
     * @throws \Throwable
     */
    public function run(string $name = self::DEFAULT_TASK_QUEUE): int
    {
        $this->bootExecutorRoutes($name);

        try {
            while ($request = $this->transport->waitForMessage()) {
                $this->transport->send(
                    $this->protocol->next($request)
                );

                $this->tick();
            }
        } catch (\Throwable $e) {
            $this->throw($e);

            return $e->getCode() ?: 1;
        }

        return 0;
    }

    /**
     * @param string $taskQueue
     * @return void
     */
    private function bootExecutorRoutes(string $taskQueue): void
    {
        $this->router->add(new GetWorkerInfo($this, $this->id, $taskQueue), true);
    }
}
