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
use Temporal\Client\Internal\Coroutine\CoroutineInterface;
use Temporal\Client\Internal\Coroutine\Stack;
use Temporal\Client\Worker\Command\RequestInterface;
use Temporal\Client\Worker\LoopInterface;
use Temporal\Client\Workflow;
use Temporal\Client\Workflow\CancellationScopeInterface;
use Temporal\Client\Workflow\ContextInterface;

/**
 * @internal Scope is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 */
abstract class Scope implements CancellationScopeInterface
{
    /**
     * @var ContextInterface
     */
    private ContextInterface $context;

    /**
     * @var CoroutineInterface
     */
    private CoroutineInterface $process;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @var Deferred
     */
    private Deferred $deferred;

    /**
     * @param ContextInterface $context
     * @param callable $handler
     * @param array $arguments
     */
    public function __construct(ContextInterface $context, LoopInterface $loop, callable $handler, array $args = [])
    {
        $this->context = $context;
        $this->loop = $loop;

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
            //
            \error_log('CANCEL!!!!!!!!!!!!!!!!!!!!!!');
        };
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
            $this->context->complete($this->process->getReturn());

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
     * @param PromiseInterface $promise
     */
    private function nextPromise(PromiseInterface $promise): void
    {
        $onFulfilled = function ($result) {
            $this->loop->once(LoopInterface::ON_TICK, function () use ($result) {
                $this->makeCurrent();
                $this->process->send($result);
                $this->next();
            });

            return $result;
        };

        $onRejected = function (\Throwable $e) {
            $this->loop->once(LoopInterface::ON_TICK, function () use ($e) {
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
    public function cancel(): void
    {
        $promise = $this->deferred->promise();
        $promise->cancel();
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
