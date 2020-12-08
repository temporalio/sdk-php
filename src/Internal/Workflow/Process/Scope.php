<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Workflow\Process;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Promise\PromisorInterface;
use Temporal\Client\Exception\CancellationException;
use Temporal\Client\Internal\Coroutine\CoroutineInterface;
use Temporal\Client\Internal\Coroutine\Stack;
use Temporal\Client\Internal\ServiceContainer;
use Temporal\Client\Internal\Transport\ClientInterface;
use Temporal\Client\Internal\Transport\Request\Cancel;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Worker\LoopInterface;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\CancellationScopeInterface;
use Temporal\Client\Workflow\WorkflowContext;

use function React\Promise\resolve;

/**
 * @internal Scope is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 */
abstract class Scope implements CancellationScopeInterface
{
    /**
     * @var WorkflowContext
     */
    protected WorkflowContext $context;

    /**
     * @var CoroutineInterface
     */
    protected CoroutineInterface $process;

    /**
     * @var Deferred
     */
    private Deferred $deferred;

    /**
     * @var ServiceContainer
     */
    protected ServiceContainer $services;

    /**
     * @param WorkflowContext $ctx
     * @param callable $handler
     * @param array $arguments
     */
    public function __construct(
        WorkflowContext $ctx,
        ServiceContainer $services,
        callable $handler,
        array $args = []
    ) {
        $this->context = $ctx;
        $this->services = $services;
        $this->deferred = new Deferred($this->canceller());

        try {
            $this->process = new Stack($this->call($handler, $args), function ($result) {
                $this->deferred->resolve($result);
            });
        } catch (\Throwable $e) {
            $this->deferred->reject($e);
        }

        $this->next();
    }

    /**
     * @return \Closure
     */
    private function canceller(): \Closure
    {
        return function () {
            $this->cancel();
        };
    }

    /**
     * @return void
     */
    public function cancel(): void
    {
        foreach ($this->fetchUnresolvedRequests() as $promise) {
            $promise->cancel();
        }

        $this->deferred->reject(CancellationException::fromScope($this));
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

        $result = $handler(...$args);

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

        if (! $this->process->valid()) {
            $this->onComplete($this->process->getReturn());

            return;
        }

        $current = $this->process->current();

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
                $this->process->push($current);
                break;

            default:
                $this->process->send($current);
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
            $this->services->loop->once(LoopInterface::ON_TICK, function () use ($result) {
                $this->makeCurrent();
                $this->process->send($result);
                $this->next();
            });

            return $result;
        };

        $onRejected = function (\Throwable $e) {
            $this->services->loop->once(LoopInterface::ON_TICK, function () use ($e) {
                $this->makeCurrent();
                $this->process->throw($e);
            });

            throw $e;
        };

        $promise->then($onFulfilled, $onRejected);
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
