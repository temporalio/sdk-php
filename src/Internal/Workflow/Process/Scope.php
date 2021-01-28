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
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Exception\Failure\TemporalFailure;
use Temporal\Internal\Coroutine\CoroutineInterface;
use Temporal\Internal\Coroutine\Stack;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Request\Cancel;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\WorkflowContext;

/**
 * Unlike Java implementation, PHP merged coroutine and cancellation scope into single instance.
 *
 * @internal CoroutineScope is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 */
class Scope implements CancellationScopeInterface, PromisorInterface
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
     * @var Deferred
     */
    protected Deferred $deferred;

    /**
     * @var CoroutineInterface
     */
    protected CoroutineInterface $coroutine;

    /**
     * Due nature of PHP generators the result of coroutine can be available before all child coroutines complete.
     * This property will hold this result until all the inner coroutines resolve.
     *
     * @var mixed
     */
    private $result;

    /**
     * When scope completes with exception.
     *
     * @var \Throwable|null
     */
    private ?\Throwable $exception = null;

    /**
     * Every coroutine runs on it's own loop layer.
     *
     * @var string
     */
    private string $layer = LoopInterface::ON_TICK;

    /**
     * When wait complete reaches 0 the result (or exception) will be resolved to parent scope. Waits for inner coroutines
     * and confirmations of Cancel commands. Internal coroutine creates single lock as well.
     *
     * @var int
     */
    private int $awaitLock;

    /**
     * @var callable
     */
    private $unlock;

    /**
     * Each onCancel receives unique ID.
     *
     * @var int
     */
    private int $cancelID = 0;

    /**
     * @var array<callable>
     */
    private array $onCancel = [];

    /**
     * @var array<callable>
     */
    private array $onClose = [];

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

        $this->awaitLock = 0;
        $this->unlock = function () {
            $this->unlock();
        };
    }

    /**
     * @return string
     */
    public function getLayer(): string
    {
        return $this->layer;
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
     * @param callable $handler
     * @param ValuesInterface|null $values
     */
    public function start(callable $handler, ValuesInterface $values = null)
    {
        try {
            $this->awaitLock++;
            $this->coroutine = new Stack($this->call($handler, $values ?? EncodedValues::empty()));
            $this->context->resolveConditions();
        } catch (\Throwable $e) {
            $this->onException($e);
            return;
        }

        $this->next();
    }

    /**
     * {@inheritDoc}
     */
    public function onCancel(callable $then): self
    {
        $this->onCancel[++$this->cancelID] = $then;
        return $this;
    }

    /**
     * @param callable $then An exception instance is passed in case of error.
     * @return $this
     */
    public function onClose(callable $then): self
    {
        $this->onClose[] = $then;
        return $this;
    }

    /**
     * @param \Throwable|null $reason
     */
    public function cancel(\Throwable $reason = null): void
    {
        if ($this->detached && !$reason instanceof DestructMemorizedInstanceException) {
            // detaches scopes can be offload via memory flush
            return;
        }

        if ($this->cancelled) {
            return;
        }
        $this->cancelled = true;

        foreach ($this->onCancel as $i => $handler) {
            $this->makeCurrent();
            $handler($reason);
            unset($this->onCancel[$i]);
        }
    }

    /**
     * @param bool $detached
     * @param string|null $layer
     * @return self
     */
    protected function createScope(bool $detached, string $layer = null): self
    {
        $scope = new Scope($this->services, $this->context);
        $scope->detached = $detached;

        if ($layer !== null) {
            $scope->layer = $layer;
        }

        // do not return parent scope result until inner scope complete
        $this->awaitLock++;
        $scope->promise()->then($this->unlock, $this->unlock);

        $cancelID = ++$this->cancelID;
        $this->onCancel[$cancelID] = \Closure::fromCallable([$scope, 'cancel']);

        $scope->onClose(
            function () use ($cancelID) {
                unset($this->onCancel[$cancelID]);
            }
        );

        return $scope;
    }

    /**
     * @param callable $handler
     * @param bool $detached
     * @param string|null $layer
     * @return CancellationScopeInterface
     */
    public function startScope(callable $handler, bool $detached, string $layer = null): CancellationScopeInterface
    {
        $scope = $this->createScope($detached, $layer);
        $scope->start($handler);

        return $scope;
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

    /**
     * @param callable $handler
     * @param ValuesInterface|null $values
     * @return \Generator
     */
    protected function call(callable $handler, ValuesInterface $values): \Generator
    {
        $this->makeCurrent();
        $result = $handler($values);

        if ($result instanceof \Generator || $result instanceof CoroutineInterface) {
            yield from $result;

            return $result->getReturn();
        }

        return $result;
    }

    /**
     * @param RequestInterface $request
     * @param PromiseInterface $promise
     */
    protected function onRequest(RequestInterface $request, PromiseInterface $promise)
    {
        if (!$request->isCancellable()) {
            return;
        }

        $this->onCancel[++$this->cancelID] = function (\Throwable $reason = null) use ($request) {
            if ($reason instanceof DestructMemorizedInstanceException) {
                // memory flush
                $this->context->getClient()->reject($request, $reason);
                return;
            }

            if ($this->context->getClient()->isQueued($request)) {
                $this->context->getClient()->cancel($request);
                return;
            }

            $this->awaitLock++;
            $this->context->getClient()->request(new Cancel($request->getID()))->then($this->unlock, $this->unlock);
        };

        $cancelID = $this->cancelID;

        // do not cancel already complete promises
        $cleanup = function () use ($cancelID) {
            unset($this->onCancel[$cancelID]);
        };

        $promise->then($cleanup, $cleanup);
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
        $this->context->resolveConditions();

        if (!$this->coroutine->valid()) {
            $this->onResult($this->coroutine->getReturn());

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
                $this->nextPromise($this->context->getClient()->request($current));
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
                    try {
                        $this->coroutine->send($result);
                        $this->next();
                    } catch (\Throwable $e) {
                        $this->onException($e);
                        return;
                    }
                }
            );

            return $result;
        };

        $onRejected = function (\Throwable $e) {
            $this->defer(
                function () use ($e) {
                    $this->makeCurrent();

                    if ($e instanceof TemporalFailure && !$e->hasOriginalStackTrace()) {
                        $e->setOriginalStackTrace($this->context->getStackTrace());
                    }

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
    private function onException(\Throwable $e): void
    {
        $this->exception = $e;
        $this->unlock();
    }

    /**
     * @param mixed $result
     */
    private function onResult($result): void
    {
        $this->result = $result;
        $this->unlock();
    }

    /**
     * Unlocks scope and pushes the result to the parent.
     */
    protected function unlock(): void
    {
        $this->makeCurrent();
        $this->context->resolveConditions();

        $this->awaitLock--;
        if ($this->awaitLock < 0) {
            throw new \LogicException("Undefined wait lock removed");
        }

        if ($this->awaitLock !== 0) {
            // not ready yes
            return;
        }

        if ($this->exception !== null) {
            $this->deferred->reject($this->exception);
        } else {
            $this->deferred->resolve($this->result);
        }

        foreach ($this->onClose as $close) {
            $close($this->exception);
        }
    }

    /**
     * @param \Closure $tick
     * @return mixed
     */
    private function defer(\Closure $tick)
    {
        $listener = $this->services->loop->once($this->layer, $tick);

        if ($this->services->queue->count() === 0) {
            $this->services->loop->tick();
        }

        return $listener;
    }
}
