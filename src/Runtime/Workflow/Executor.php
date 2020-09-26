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
use React\Promise\PromiseInterface;
use Temporal\Client\Protocol\ClientInterface;
use Temporal\Client\Protocol\Message\RequestInterface;
use Temporal\Client\Runtime\Queue\EntryInterface;
use Temporal\Client\Runtime\Queue\RequestQueue;
use Temporal\Client\Runtime\Queue\RequestQueueInterface;
use Temporal\Client\Runtime\Request\CompleteWorkflow;
use Temporal\Client\Runtime\WorkflowContext;
use Temporal\Client\Runtime\WorkflowContextInterface;

/**
 * @psalm-import-type WorkflowContextParams from StartWorkflow
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
     * @var bool
     */
    private bool $completed = false;

    /**
     * @param ClientInterface $client
     * @param array $params
     * @param Deferred $resolver
     */
    public function __construct(ClientInterface $client, array $params, Deferred $resolver)
    {
        $this->params = $params;
        $this->client = $client;
        $this->resolver = $resolver;
        $this->queue = new RequestQueue();
        $this->context = new WorkflowContext($params, $this->queue);
    }

    /**
     * @param \Closure $handler
     */
    public function execute(\Closure $handler): void
    {
        // TODO auto resolve parameters instead of "$this->context" passing.
        $response = $handler($this->context);

        switch (true) {
            case $response instanceof \Generator:
                $this->processCoroutine($response);
                break;

            default:
                $this->processResult($response);
        }
    }

    /**
     * @param \Generator $coroutine
     */
    private function processCoroutine(\Generator $coroutine): void
    {
        if ($coroutine->valid()) {
            $current = $coroutine->current();

            switch (true) {
                case $current instanceof PromiseInterface:
                    $this->processPromise($coroutine, $current);
                    break;

                case \is_iterable($current):
                    // TODO process group of promises

                default:
                    $error = \sprintf('Unsupported coroutine data: %s', \get_debug_type($current));
                    $coroutine->throw(new \InvalidArgumentException($error));
            }

            return;
        }

        $this->resolver->resolve($coroutine->getReturn());
    }

    /**
     * @param \Generator $coroutine
     * @param PromiseInterface $promise
     */
    private function processPromise(\Generator $coroutine, PromiseInterface $promise): void
    {
        $entry = $this->queue->pull($promise);

        if ($entry === null) {
            $error = 'The passed Promise object is not part of the workflow executable context';
            $coroutine->throw(new \InvalidArgumentException($error));
        }

        //
        // In the case of a regular request, we should subscribe to the promise
        // resolving. And after resolving the promise, we should call the next
        // tick of the coroutine using "send()" with the transfer of the result
        // of the promise. Thus, go to the next task of the generator.
        //
        // In the case of a "CompleteWorkflow" request, we should stop the
        // generator execution.
        //
        if ($this->isCompletion($entry->request)) {
            $promise->then(
                fn($result) => $this->resolve($result),
                fn (\Throwable $e) => $this->reject($e)
            );
        } else {
            $this->nextCoroutineTickAfterResolving($coroutine, $promise);
        }

        //
        // Send the request and after the response resolve the Promise from
        // the requests queue.
        //
        $this->client->request($entry->request)
            ->then(
                fn($response) => $entry->resolver->resolve($response),
                fn(\Throwable $error) => $entry->resolver->reject($error),
            )
        ;
    }

    /**
     * @param \Generator $coroutine
     * @param PromiseInterface $promise
     */
    private function nextCoroutineTickAfterResolving(\Generator $coroutine, PromiseInterface $promise): void
    {
        $onFulfilled = function ($result) use ($coroutine) {
            $coroutine->send($result);

            $this->processCoroutine($coroutine);
        };

        $onRejected = fn(\Throwable $e) => $coroutine->throw($e);

        $promise->then($onFulfilled, $onRejected);
    }

    /**
     * @param mixed $result
     * @return void
     */
    private function processResult($result): void
    {
        /** @var EntryInterface $entry */
        foreach ($this->queue as $entry) {
            $request = $entry->request;

            $this->client->request($entry->request)
                ->then(function ($response) use ($entry) {
                    $entry->resolver->resolve($response);
                })
            ;

            //
            // Do not process the rest of the queue tasks if a
            // request has arrived for workflow completion.
            //
            if ($this->isCompletion($request)) {
                $this->resolve($this->getCompletionResult($request));
                break;
            }
        }

        $this->resolve($result);
    }

    /**
     * @param RequestInterface $request
     * @return bool
     */
    private function isCompletion(RequestInterface $request): bool
    {
        return $request->getMethod() === CompleteWorkflow::METHOD_NAME;
    }

    /**
     * @param mixed $result
     */
    private function resolve($result): void
    {
        if ($this->completed === false) {
            $this->completed = true;
            $this->resolver->resolve($result);
        }
    }

    /**
     * @param RequestInterface $request
     * @return mixed|null
     */
    private function getCompletionResult(RequestInterface $request)
    {
        $params = $request->getParams();

        return $params[CompleteWorkflow::PARAM_RESULT] ?? null;
    }

    /**
     * @param \Throwable $e
     */
    private function reject(\Throwable $e): void
    {
        if ($this->completed === false) {
            $this->completed = true;
            $this->resolver->reject($e);
        }
    }
}
