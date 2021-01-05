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
     * @var ServiceContainer
     */
    protected ServiceContainer $services;

    /**
     * @var array<callable>
     */
    protected array $cancelHandlers = [];

    /**
     * @var bool
     */
    private bool $cancelled = false;

    /**
     * @param WorkflowContext $ctx
     * @param ServiceContainer $services
     * @param callable $handler
     * @param array $args
     */
    public function __construct(
        WorkflowContext $ctx,
        ServiceContainer $services,
        callable $handler,
        array $args = []
    ) {
        $this->context = $ctx;
        $this->services = $services;
        $this->deferred = new Deferred(
            function () {
                foreach ($this->cancelHandlers as $handler) {
                    $handler($this);
                }

                $this->deferred->reject(CancellationException::fromScope($this));
            }
        );

        try {
            $this->coroutine = new Stack(
                $this->call($handler, $args), function ($result) {
                $this->deferred->resolve($result);
            }
            );
        } catch (\Throwable $e) {
            $this->deferred->reject($e);
        }
    }

    private array $child = [];

    /**
     * @param Scope $scope
     */
    public function linkChild(Scope $scope)
    {
        $this->child[] = $scope;
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
    public function onCancel(callable $then): self
    {
        $this->cancelHandlers[] = $then;

        return $this;
    }

    /**
     * @return void
     */
    public function cancel(): void
    {
        if ($this->cancelled) {
            return;
        }

        $this->cancelled = true;
        try {
            // improve hieharhy
            $this->context->invalidate();

            foreach ($this->child as $child) {
                $child->cancel();
            }

            $promise = $this->promise();
            $promise->cancel();
        } finally {
            foreach ($this->fetchUnresolvedRequests() as $promise) {
                $promise->cancel();
            }
        }
    }

    /**
     * @return array<positive-int, PromiseInterface>
     */
    public function fetchUnresolvedRequests(): array
    {
        $client = $this->context->getClient();

        return $client->fetchUnresolvedRequests();
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
     * @return void
     */
    protected function makeCurrent(): void
    {
        Workflow::setCurrentContext($this->context);
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
     * @param mixed $result
     */
    abstract protected function onComplete($result): void;

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
     * @param \Throwable $e
     */
    protected function onException(\Throwable $e)
    {
        if ($e instanceof CancellationException) {
            $this->cancel();
        }
    }

    /**
     * @param \Closure $tick
     * @return mixed
     */
    private function defer(\Closure $tick)
    {
        $listener = $this->services->loop->once(LoopInterface::ON_TICK, $tick);

        if ($this->services->queue->count() === 0) {
            $this->services->loop->tick();
        }

        return $listener;
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
