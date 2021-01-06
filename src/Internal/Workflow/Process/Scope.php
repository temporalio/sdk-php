<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Promise\PromisorInterface;
use Temporal\Exception\CancellationException;
use Temporal\Internal\Coroutine\CoroutineInterface;
use Temporal\Internal\Coroutine\Stack;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Worker\Command\RequestInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\WorkflowContext;

/**
 * @internal Scope is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 */
abstract class Scope implements CancellationScopeInterface, PromisorInterface
{
    /**
     * @var ServiceContainer
     */
    protected ServiceContainer $services;

    /**
     * @var WorkflowContext
     */
    protected WorkflowContext $context;

    /**
     * @var CoroutineInterface
     */
    protected CoroutineInterface $coroutine;

    /**
     * @var Deferred
     */
    private Deferred $deferred;

    /**
     * @var array<Scope>
     */
    private array $child = [];

    /**
     * @var array<callable>
     */
    private array $onCancel = [];

    /**
     * @var bool
     */
    private bool $detached = false;

    /**
     * @var bool
     */
    private bool $cancelled = false;

    /**
     * @param WorkflowContext $ctx
     * @param ServiceContainer $services
     */
    public function __construct(ServiceContainer $services, WorkflowContext $ctx)
    {
        $this->context = $ctx;
        $this->services = $services;
        $this->deferred = new Deferred();
    }

    /**
     * @return bool
     */
    public function isDetached(): bool
    {
        return $this->detached;
    }

    /**
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * @return WorkflowContext
     */
    public function getContext(): WorkflowContext
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     */
    public function onCancel(callable $then): self
    {
        $this->onCancel[] = $then;
        return $this;
    }

    /**
     * Start the scope.
     */
    public function start(callable $handler, array $args)
    {
        try {
            $this->coroutine = new Stack(
                $this->call($handler, $args), function ($result) {
                $this->deferred->resolve($result);
            }
            );
        } catch (\Throwable $e) {
            $this->deferred->reject($e);
        }

        $this->next();
    }

    /**
     * @param callable $handler
     * @param array $args
     * @return \Generator
     */
    protected function call(callable $handler, array $args): \Generator
    {
        $this->makeCurrent();

        $result = $handler($args);

        if ($result instanceof \Generator || $result instanceof CoroutineInterface) {
            yield from $result;

            return $result->getReturn();
        }

        return $result;
    }

    /**
     * @param callable $handler
     * @param bool $detached
     * @return CancellationScopeInterface
     */
    abstract public function createScope(callable $handler, bool $detached): CancellationScopeInterface;

    /**
     * @return void
     */
    public function cancel(): void
    {
        if ($this->cancelled) {
            throw new \LogicException("Unable to cancel already cancelled scope");
        }
        $this->cancelled = true;

        foreach ($this->onCancel as $trigger) {
            $trigger();
        }

        // todo: simple trigger

        // todo: can be ommited later


//        try {
//            foreach ($this->childScopes as $child) {
//                $child->cancel();
//            }
//
//            // todo: called in finalize (!!!!!!)
//            $promise = $this->promise();
//            $promise->cancel();
//        } finally {
//            foreach ($this->fetchUnresolvedRequests() as $promise) {
//                $promise->cancel();
//            }
//        }
    }

    /**
     * @param mixed $result
     */
    abstract protected function onComplete($result): void;

    /**
     * @param \Throwable $e
     */
    abstract protected function onException(\Throwable $e);

    /**
     * Collect all requests created within the scope to later cancel them.
     *
     * @param RequestInterface $request
     */
    protected function onRequest(RequestInterface $request)
    {
        // todo: filter requests
        error_log("GOT REQUEST!!!" . get_class($request));

        // Otherwise, we send a Cancel request to the server to cancel
        // the previously sent command by its ID.
//                $this->request(new Cancel([$id]))->then(
//                    function () use ($id) {
//                        $request = $this->get($id);
//                        $request->reject(CancellationException::fromRequestId($id));
//                    }
//                );
    }

    /**
     * @return void
     */
    protected function makeCurrent(): void
    {
        Workflow::setCurrentContext(
            ScopeContext::fromWorkflowContext(
                $this->context,
                $this,
                \Closure::fromCallable([$this, 'onRequest'])
            )
        );
    }

    /**
     * @return void
     */
    protected function next(): void
    {
        $this->makeCurrent();

        if (!$this->coroutine->valid()) {
            $this->onComplete($this->coroutine->getReturn());

            return;
        }

        $current = $this->coroutine->current();

        switch (true) {
            case $current instanceof PromiseInterface:
                $this->nextPromise($current);
                break;

            case $current instanceof PromisorInterface:
                $this->nextPromise($current->promise());
                break;

            case $current instanceof RequestInterface:
                $this->nextPromise($this->context->request($current));
                break;

            case $current instanceof \Generator:
            case $current instanceof CoroutineInterface:
                $this->coroutine->push($current);
                break;

            default:
                $this->coroutine->send($current);
        }
    }

    /**
     * @param PromiseInterface $promise
     */
    private function nextPromise(PromiseInterface $promise): void
    {
        $onFulfilled = function ($result) {
            $this->defer(
                function () use ($result) {
                    $this->makeCurrent();
                    $this->coroutine->send($result);
                    $this->next();
                }
            );

            return $result;
        };

        $onRejected = function (\Throwable $e) {
            $this->defer(
                function () use ($e) {
                    $this->makeCurrent();

                    try {
                        $this->coroutine->throw($e);
                    } catch (\Throwable $e) {
                        $this->onException($e);
                        return;
                    }

                    $this->next();
                }
            );

            throw $e;
        };

        $promise->then($onFulfilled, $onRejected);
    }

    /**
     * @param \Closure $tick
     * @return mixed
     */
    private function defer(\Closure $tick)
    {
        $listener = $this->services->loop->once(LoopInterface::ON_TICK, $tick);

//        if ($this->services->queue->count() === 0) {
//            // todo: what is that?
//            $this->services->loop->tick();
//        }

        return $listener;
    }

    /**
     * {@inheritDoc}
     */
    public function promise(): PromiseInterface
    {
        return $this->deferred->promise();
    }

    /**
     * {@inheritDoc}
     */
    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null,
        callable $onProgress = null
    ): PromiseInterface {
        $promise = $this->deferred->promise();

        return $promise->then($onFulfilled, $onRejected, $onProgress);
    }
}
