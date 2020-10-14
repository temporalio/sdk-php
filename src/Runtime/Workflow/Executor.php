<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Runtime\Workflow;

use React\Promise\Deferred;
use React\Promise\ExtendedPromiseInterface;
use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Protocol\Command\Request;
use Temporal\Client\Runtime\Queue\EntryInterface;
use Temporal\Client\Runtime\Queue\RequestQueue;
use Temporal\Client\Runtime\Queue\RequestQueueInterface;
use Temporal\Client\Runtime\WorkflowContext;
use Temporal\Client\Runtime\WorkflowContextInterface;
use Temporal\Client\Worker\ExecutorInterface;
use Temporal\Client\Worker\Route\StartWorkflow;

/**
 * @psalm-import-type WorkflowContextParams from StartWorkflow
 * @see StartWorkflow
 */
class Executor
{
    /**
     * @var array
     */
    private array $params;

    /**
     * @var Deferred
     */
    private Deferred $resolver;

    /**
     * @var RequestQueueInterface
     */
    private RequestQueueInterface $queue;

    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var array
     */
    private array $entries = [];

    /**
     * @var ExecutorInterface
     */
    private ExecutorInterface $executor;

    /**
     * @param ExecutorInterface $executor
     * @param ClientInterface $client
     * @param array $params
     * @param Deferred $resolver
     */
    public function __construct(ExecutorInterface $executor, ClientInterface $client, array $params, Deferred $resolver)
    {
        $this->params = $params;
        $this->client = $client;
        $this->resolver = $resolver;
        $this->executor = $executor;

        $this->queue = new RequestQueue();
        $this->context = new WorkflowContext($params, $this->queue);
    }

    /**
     * @param \Closure $handler
     */
    public function execute(\Closure $handler): void
    {
        // TODO auto resolve parameters instead of "$this->context" passing.
        $result = $handler($this->context);

        if ($result instanceof \Generator) {
            $this->processNextCoroutineTick($result);
        }
        // If not
    }

    /**
     * @param \Generator $coroutine
     */
    private function processNextCoroutineTick(\Generator $coroutine): void
    {
        if (! $coroutine->valid()) {
            $this->context->complete($coroutine->getReturn())
                ->then(function ($result) {
                    $this->resolver->resolve($result);
                });

            return;
        }

        /** @var ExtendedPromiseInterface $promise */
        $promise = $coroutine->current();

        $promise->then(function ($result) use ($coroutine) {
            $coroutine->send($result);

            $this->processNextCoroutineTick($coroutine);
        });

        while (! $this->queue->isEmpty()) {
            $this->sendRequests();
        }
    }

    /**
     * @return void
     */
    private function sendRequests(): void
    {
        /** @var EntryInterface[] $entries */
        $entries = [...$this->queue];

        $requests = \array_map(static fn (EntryInterface $e) => $e->request, $entries);
        $responses = $this->client->request(...$requests);

        foreach ($responses as $i => $promise) {
            /** @var EntryInterface $current */
            $this->entries[] = $current = $entries[$i];
            $last = \array_key_last($this->entries);

            $then = function ($result) use ($current, $last) {
                unset($this->entries[$last]);

                if (\count($this->entries)) {
                    $this->client->request(new Request('NextTick'));
                }

                $current->resolver->resolve($result);

                while (! $this->queue->isEmpty()) {
                    $this->sendRequests();
                }

                return $result;
            };

            $promise->then(
                $then,
                fn (\Throwable $e) => $current->resolver->reject($e),
            );
        }
    }
}
