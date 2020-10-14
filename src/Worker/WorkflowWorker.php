<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

use React\Promise\Deferred;
use Temporal\Client\Declaration\WorkflowInterface;
use Temporal\Client\Meta\ReaderInterface;
use Temporal\Client\Protocol\Command\RequestInterface;
use Temporal\Client\Protocol\ProtocolInterface;
use Temporal\Client\Protocol\WorkflowProtocol;
use Temporal\Client\Protocol\WorkflowProtocolInterface;
use Temporal\Client\Transport\TransportInterface;
use Temporal\Client\Worker\Route\InitWorker;

class WorkflowWorker extends Worker implements WorkflowWorkerInterface
{
    use WorkflowProviderTrait;

    /**
     * @param ReaderInterface $reader
     * @param TransportInterface $transport
     * @param iterable|WorkflowInterface[] $workflows
     * @throws \Exception
     */
    public function __construct(ReaderInterface $reader, TransportInterface $transport, iterable $workflows)
    {
        $this->bootWorkflowProviderTrait();

        parent::__construct($reader, $transport);

        $this->bootWorkflows($workflows);
    }

    /**
     * @param iterable|WorkflowInterface[] $workflows
     */
    private function bootWorkflows(iterable $workflows): void
    {
        foreach ($workflows as $workflow) {
            $this->addWorkflow($workflow);
        }
    }

    /**
     * @param RouterInterface $router
     * @return WorkflowProtocolInterface
     * @throws \Exception
     */
    private function createProtocol(RouterInterface $router): WorkflowProtocolInterface
    {
        return new WorkflowProtocol(function (RequestInterface $request, Deferred $deferred) use ($router) {
            $router->emit($request, $deferred);
        });
    }

    /**
     * @param string $name
     * @return int
     * @throws \Throwable
     */
    public function run(string $name = self::DEFAULT_WORKER_ID): int
    {
        $router = new Router();
        $router->add(new InitWorker($this, $this->pool, $name));

        $protocol = $this->createProtocol($router);

        try {
            while ($request = $this->transport->waitForMessage()) {
                $this->transport->send(
                    $protocol->next($request)
                );
            }
        } catch (\Throwable $e) {
            $this->throw($e);

            return $e->getCode() ?: 1;
        }

        return 0;
    }
}
