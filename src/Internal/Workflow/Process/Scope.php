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
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\TemporalFailure;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Request\Cancel;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\WorkflowContextInterface;

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
     * @var WorkflowContextInterface
     */
    protected WorkflowContextInterface $context;

    /**
     * @var WorkflowContextInterface
     */
    protected WorkflowContextInterface $scopeContext;

    /**
     * @var Deferred
     */
    protected Deferred $deferred;

    /**
     * @var \Generator
     */
    protected \Generator $coroutine;

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
     * @param WorkflowContext  $ctx
     * @param ServiceContainer $services
     */
    public function __construct(ServiceContainer $services, WorkflowContext $ctx)
    {
        $this->context = $ctx;
        $this->scopeContext = ScopeContext::fromWorkflowContext(
            $this->context,
            $this,
            \Closure::fromCallable([$this, 'onRequest'])
        );

        $this->services = $services;
        $this->deferred = new Deferred();
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
     * @param callable             $handler
     * @param ValuesInterface|null $values
     */
    public function start(callable $handler, ValuesInterface $values = null): void
    {
        try {
            $this->coroutine = $this->call($handler, $values ?? EncodedValues::empty());
            $this->context->resolveConditions();
        } catch (\Throwable $e) {
            $this->onException($e);
            return;
        }

        $this->next();
    }

    /**
     * @param \Generator $generator
     * @return self
     */
    public function attach(\Generator $generator): self
    {
        $this->coroutine = $generator;
        $this->context->resolveConditions();

        $this->next();
        return $this;
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
     * @param callable    $handler
     * @param bool        $detached
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
     * Connects promise to scope context to be cancelled on promise cancel.
     *
     * @param Deferred $deferred
     */
    public function onAwait(Deferred $deferred): void
    {
        $this->onCancel[++$this->cancelID] = function (\Throwable $e = null) use ($deferred): void {
            $deferred->reject($e ?? new CanceledFailure(''));
        };

        $cancelID = $this->cancelID;


        // do not cancel already complete promises
        $cleanup = function () use ($cancelID): void {
            $this->context->resolveConditions();
            unset($this->onCancel[$cancelID]);
        };

        $deferred->promise()->then($cleanup, $cleanup);
    }

    /**
     * @param bool        $detached
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

        $cancelID = ++$this->cancelID;
        $this->onCancel[$cancelID] = \Closure::fromCallable([$scope, 'cancel']);

        $scope->onClose(
            function () use ($cancelID): void {
                unset($this->onCancel[$cancelID]);
            }
        );

        return $scope;
    }

    /**
     * @param callable $handler
     * @param ValuesInterface $values
     * @return \Generator
     */
    protected function call(callable $handler, ValuesInterface $values): \Generator
    {
        $this->makeCurrent();
        $result = $handler($values);

        if ($result instanceof \Generator) {
            yield from $result;

            return $result->getReturn();
        }

        return $result;
    }

    /**
     * @param RequestInterface $request
     * @param PromiseInterface $promise
     */
    protected function onRequest(RequestInterface $request, PromiseInterface $promise): void
    {
        $this->onCancel[++$this->cancelID] = function (\Throwable $reason = null) use ($request): void {
            if ($reason instanceof DestructMemorizedInstanceException) {
                // memory flush
                $this->context->getClient()->reject($request, $reason);
                return;
            }

            if ($this->context->getClient()->isQueued($request)) {
                $this->context->getClient()->cancel($request);
                return;
            }

            $this->context->getClient()->request(new Cancel($request->getID()));
        };

        $cancelID = $this->cancelID;

        // do not cancel already complete promises
        $cleanup = function () use ($cancelID): void {
            $this->context->resolveConditions();
            unset($this->onCancel[$cancelID]);
        };

        $promise->then($cleanup, $cleanup);
    }

    /**
     * @return void
     */
    protected function makeCurrent(): void
    {
        Workflow::setCurrentContext($this->scopeContext);
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
                try {
                    $this->nextPromise($this->createScope(false)->attach($current));
                } catch (\Throwable $e) {
                    $this->coroutine->throw($e);
                }
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
        if ($promise instanceof CancellationScopeInterface && $promise->isCancelled()) {
            $this->handleError(new CanceledFailure(''));
            return;
        }

        $onFulfilled = function ($result) {
            $this->defer(
                function () use ($result): void {
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

        $onRejected = function (\Throwable $e): void {
            $this->defer(
                function () use ($e): void {
                    if ($e instanceof TemporalFailure && !$e->hasOriginalStackTrace()) {
                        $e->setOriginalStackTrace($this->context->getStackTrace());
                    }

                    $this->handleError($e);
                }
            );

            throw $e;
        };

        $promise->then($onFulfilled, $onRejected);
    }

    /**
     * Send error into the coroutine. If the code inside handles exception
     * we continue the flow. If the exception is bubbled up - the scope
     * itself handles it.
     */
    private function handleError(\Throwable $e): void
    {
        $this->makeCurrent();

        try {
            $this->coroutine->throw($e);
        } catch (\Throwable $e) {
            $this->onException($e);
            return;
        }

        $this->next();
    }

    /**
     * @param \Throwable $e
     */
    private function onException(\Throwable $e): void
    {
        $this->exception = $e;
        $this->deferred->reject($e);

        $this->makeCurrent();
        $this->context->resolveConditions();

        foreach ($this->onClose as $close) {
            $close($this->exception);
        }
    }

    /**
     * @param mixed $result
     */
    private function onResult($result): void
    {
        $this->result = $result;
        $this->deferred->resolve($result);

        $this->makeCurrent();
        $this->context->resolveConditions();

        foreach ($this->onClose as $close) {
            $close($this->result);
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
