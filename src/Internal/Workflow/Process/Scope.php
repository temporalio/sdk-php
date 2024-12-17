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
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\DestructMemorizedInstanceException;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Exception\Failure\TemporalFailure;
use Temporal\Exception\InvalidArgumentException;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Internal\Declaration\Destroyable;
use Temporal\Internal\ServiceContainer;
use Temporal\Internal\Transport\Request\Cancel;
use Temporal\Internal\Workflow\ScopeContext;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Worker\LoopInterface;
use Temporal\Worker\Transport\Command\RequestInterface;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;

/**
 * Unlike Java implementation, PHP has merged coroutine and cancellation scope into a single instance.
 *
 * @internal CoroutineScope is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Internal
 * @implements CancellationScopeInterface<mixed>
 */
class Scope implements CancellationScopeInterface, Destroyable
{
    protected ServiceContainer $services;

    /**
     * Workflow context.
     *
     */
    protected WorkflowContext $context;

    /**
     * Coroutine scope context.
     *
     */
    protected ScopeContext $scopeContext;

    protected Deferred $deferred;

    /**
     * Worker handler generator that yields promises and requests that are processed in the {@see self::next()} method.
     */
    protected DeferredGenerator $coroutine;

    /**
     * Every coroutine runs on its own loop layer.
     *
     * @var non-empty-string
     */
    private string $layer = LoopInterface::ON_TICK;

    /**
     * Each onCancel receives unique ID.
     */
    private int $cancelID = 0;

    /**
     * @var array<callable>
     */
    private array $onCancel = [];

    /**
     * @var array<callable(mixed): mixed>
     */
    private array $onClose = [];

    private bool $detached = false;
    private bool $cancelled = false;

    public function __construct(
        ServiceContainer $services,
        WorkflowContext $ctx,
        ?Workflow\UpdateContext $updateContext = null,
    ) {
        $this->context = $ctx;
        $this->scopeContext = ScopeContext::fromWorkflowContext(
            $this->context,
            $this,
            $this->onRequest(...),
            $updateContext,
        );

        $this->services = $services;
        $this->deferred = new Deferred();
    }

    /**
     * @return non-empty-string
     */
    public function getLayer(): string
    {
        return $this->layer;
    }

    public function isDetached(): bool
    {
        return $this->detached;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function getContext(): WorkflowContext
    {
        return $this->context;
    }

    /**
     * @param \Closure(ValuesInterface): mixed $handler
     */
    public function start(\Closure $handler, ?ValuesInterface $values, bool $deferred): void
    {
        // Create a coroutine generator
        $this->coroutine = DeferredGenerator::fromHandler($handler, $values ?? EncodedValues::empty())
            ->catch($this->onException(...));

        $deferred
            ? $this->services->loop->once($this->layer, $this->next(...))
            : $this->next();
    }

    /**
     * @param callable(ValuesInterface): mixed $handler Update method handler.
     * @param Deferred $resolver Update method promise resolver.
     */
    public function startUpdate(callable $handler, UpdateInput $input, Deferred $resolver): void
    {
        // Update handler counter
        $id = $this->context->getHandlerState()->addUpdate($input->updateId, $input->updateName);
        $this->then(
            fn() => $this->context->getHandlerState()->removeUpdate($id),
            fn() => $this->context->getHandlerState()->removeUpdate($id),
        );

        $this->then(
            $resolver->resolve(...),
            function (\Throwable $error) use ($resolver): void {
                $this->services->exceptionInterceptor->isRetryable($error)
                    ? $this->scopeContext->panic($error)
                    : $resolver->reject($error);
            },
        );

        // Create a coroutine generator
        $this->coroutine = $this->callSignalOrUpdateHandler($handler, $input->arguments);
        $this->next();
    }

    /**
     * @param non-empty-string $name
     */
    public function startSignal(callable $handler, ValuesInterface $values, string $name): void
    {
        // Update handler counter
        $id = $this->context->getHandlerState()->addSignal($name);
        $this->then(
            fn() => $this->context->getHandlerState()->removeSignal($id),
            fn() => $this->context->getHandlerState()->removeSignal($id),
        );

        // Create a coroutine generator
        $this->coroutine = $this->callSignalOrUpdateHandler($handler, $values);
        $this->next();
    }

    /**
     * @return $this
     */
    public function attach(\Generator $generator): self
    {
        $this->coroutine = DeferredGenerator::fromGenerator($generator);

        $this->next();
        return $this;
    }

    public function onCancel(callable $then): self
    {
        $this->onCancel[++$this->cancelID] = $then;
        return $this;
    }

    /**
     * @param callable(mixed): mixed $then An exception instance is passed in case of error.
     * @return $this
     */
    public function onClose(callable $then): self
    {
        $this->onClose[] = $then;
        return $this;
    }

    public function cancel(?\Throwable $reason = null): void
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
            unset($this->onCancel[$i]);
            $handler($reason);
        }
    }

    /**
     * @param non-empty-string|null $layer
     */
    public function startScope(callable $handler, bool $detached, ?string $layer = null): CancellationScopeInterface
    {
        $scope = $this->createScope($detached, $layer);
        $scope->start($handler, null, false);

        return $scope;
    }

    public function promise(): PromiseInterface
    {
        return $this->deferred->promise();
    }

    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null,
        ?callable $onProgress = null,
    ): PromiseInterface {
        return $this->deferred->promise()->then($onFulfilled, $onRejected);
    }

    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->deferred->promise()->catch($onRejected);
    }

    public function finally(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->deferred->promise()->finally($onFulfilledOrRejected);
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->catch($onRejected);
    }

    public function always(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->finally($onFulfilledOrRejected);
    }

    /**
     * Connects promise to scope context to be cancelled on promise cancel.
     *
     */
    public function onAwait(Deferred $deferred): void
    {
        $this->onCancel[++$this->cancelID] = static function (?\Throwable $e = null) use ($deferred): void {
            $deferred->reject($e ?? new CanceledFailure(''));
        };

        $cancelID = $this->cancelID;


        // do not cancel already complete promises
        $cleanup = function () use ($cancelID): void {
            $this->makeCurrent();
            $this->context->resolveConditions();
            unset($this->onCancel[$cancelID]);
        };

        $deferred->promise()->then($cleanup, $cleanup);
    }

    public function destroy(): void
    {
        $this->scopeContext->destroy();
        $this->context->destroy();
        unset($this->coroutine);
    }

    /**
     * @param non-empty-string|null $layer
     */
    protected function createScope(
        bool $detached,
        ?string $layer = null,
        ?WorkflowContext $context = null,
        ?Workflow\UpdateContext $updateContext = null,
    ): self {
        $scope = new Scope($this->services, $context ?? $this->context, $updateContext);
        $scope->detached = $detached;

        if ($layer !== null) {
            $scope->layer = $layer;
        }

        $cancelID = ++$this->cancelID;
        $this->onCancel[$cancelID] = $scope->cancel(...);

        $scope->onClose(
            function () use ($cancelID): void {
                unset($this->onCancel[$cancelID]);
            },
        );

        return $scope;
    }

    /**
     * Call a Signal or Update method. In this case deserialization errors are skipped.
     *
     * @param callable(ValuesInterface): mixed $handler
     */
    protected function callSignalOrUpdateHandler(callable $handler, ValuesInterface $values): DeferredGenerator
    {
        return DeferredGenerator::fromHandler(static function (ValuesInterface $values) use ($handler): mixed {
            try {
                return $handler($values);
            } catch (InvalidArgumentException) {
                // Skip deserialization errors
                return null;
            }
        }, $values)->catch($this->onException(...));
    }

    protected function onRequest(RequestInterface $request, PromiseInterface $promise): void
    {
        $this->onCancel[++$this->cancelID] = function (?\Throwable $reason = null) use ($request): void {
            if ($reason instanceof DestructMemorizedInstanceException) {
                // memory flush
                $this->context->getClient()->reject($request, $reason);
                return;
            }

            if ($this->context->getClient()->isQueued($request)) {
                $this->context->getClient()->cancel($request);
                return;
            }
            // todo ->context or ->scopeContext?

            $this->context->getClient()->request(new Cancel($request->getID()), $this->scopeContext);
        };

        $cancelID = $this->cancelID;

        // do not cancel already complete promises
        $cleanup = function () use ($cancelID): void {
            $this->makeCurrent();
            $this->context->resolveConditions();
            unset($this->onCancel[$cancelID]);
        };

        $promise->then($cleanup, $cleanup);
    }

    protected function makeCurrent(): void
    {
        Workflow::setCurrentContext($this->scopeContext);
    }

    protected function next(): void
    {
        $this->makeCurrent();
        begin:
        $this->context->resolveConditions();

        if (!$this->coroutine->valid()) {
            try {
                $this->onResult($this->coroutine->getReturn());
            } catch (\Throwable) {
                $this->onResult(null);
            }

            return;
        }

        $current = $this->coroutine->current();
        $this->context->resolveConditions();

        switch (true) {
            case $current instanceof Workflow\Mutex:
                $this->nextPromise($this->context->await($current));
                break;

            case $current instanceof PromiseInterface:
                $this->nextPromise($current);
                break;

            case $current instanceof Deferred:
                $this->nextPromise($current->promise());
                break;

                // todo ->context or ->scopeContext?
            case $current instanceof RequestInterface:
                $this->nextPromise($this->context->getClient()->request($current, $this->scopeContext));
                break;

            case $current instanceof \Generator:
                try {
                    $this->nextPromise($this->createScope(false)->attach($current));
                } catch (\Throwable $e) {
                    $this->coroutine->throw($e);
                }
                break;

            default:
                try {
                    $this->coroutine->send($current);
                } catch (\Throwable) {
                    // Ignore
                }
                goto begin;
        }
    }

    private function nextPromise(PromiseInterface $promise): void
    {
        if ($promise instanceof CancellationScopeInterface && $promise->isCancelled()) {
            $this->handleError(new CanceledFailure(''));
            return;
        }

        $onFulfilled = function (mixed $result): mixed {
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
                },
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
                },
            );

            throw $e;
        };

        $promise
            ->then($onFulfilled, $onRejected)
            // Handle last error
            ->then(null, static fn(\Throwable $e) => null);
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

    private function onException(\Throwable $e): void
    {
        $this->deferred->reject($e);

        $this->makeCurrent();
        $this->context->resolveConditions();

        foreach ($this->onClose as $close) {
            $close($e);
        }
    }

    private function onResult(mixed $result): void
    {
        $this->deferred->resolve($result);

        $this->makeCurrent();
        $this->context->resolveConditions();

        foreach ($this->onClose as $close) {
            $close($result);
        }
    }

    private function defer(\Closure $tick): void
    {
        $this->services->loop->once($this->layer, $tick);
        $this->services->queue->count() === 0 and $this->services->loop->tick();
    }
}
