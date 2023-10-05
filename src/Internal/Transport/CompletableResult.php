<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowContextInterface;

/**
 * @template T of mixed
 * @implements CompletableResultInterface<T>
 */
class CompletableResult implements CompletableResultInterface
{
    /**
     * @var bool
     */
    private bool $resolved = false;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @var PromiseInterface
     */
    private PromiseInterface $promise;

    /**
     * @var Deferred
     */
    private Deferred $deferred;

    /**
     * @var string
     */
    private string $layer;

    /**
     * CompletableResult constructor.
     * @param WorkflowContextInterface $context
     * @param LoopInterface $loop
     * @param PromiseInterface $promise
     * @param string $layer
     */
    public function __construct(
        WorkflowContextInterface $context,
        LoopInterface $loop,
        PromiseInterface $promise,
        string $layer
    ) {
        $this->context = $context;
        $this->loop = $loop;
        $this->deferred = new Deferred();
        $this->layer = $layer;

        $this->promise = $promise->then(
            $this->onFulfilled(...),
            $this->onRejected(...),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isComplete(): bool
    {
        return $this->resolved;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param (callable(mixed): mixed)|null $onFulfilled
     * @param (callable(\Throwable): mixed)|null $onRejected
     * @param callable|null $onProgress
     *
     * @return PromiseInterface
     */
    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null,
        ?callable $onProgress = null,
    ): PromiseInterface {
        return $this->promise()
            ->then($this->wrapContext($onFulfilled), $this->wrapContext($onRejected));
        //return new Future($promise, $this->worker);
    }

    /**
     * @return PromiseInterface<T>
     */
    public function promise(): PromiseInterface
    {
        return $this->deferred->promise();
    }

    private function onFulfilled(mixed $result): void
    {
        $this->resolved = true;
        $this->value = $result;

        $this->loop->once(
            $this->layer,//LoopInterface::ON_CALLBACK,
            function (): void {
                Workflow::setCurrentContext($this->context);
                $this->deferred->resolve($this->value);
            }
        );
    }

    /**
     * @param \Throwable $e
     */
    private function onRejected(\Throwable $e): void
    {
        $this->resolved = true;

        $this->loop->once(
            $this->layer,//  LoopInterface::ON_CALLBACK,
            function () use ($e): void {
                Workflow::setCurrentContext($this->context);
                $this->deferred->reject($e);
            }
        );
    }

    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->promise()
            ->catch($this->wrapContext($onRejected));
    }

    public function finally(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->promise()
            ->finally($this->wrapContext($onFulfilledOrRejected));
    }

    public function cancel(): void
    {
        Workflow::setCurrentContext($this->context);
        $this->promise()->cancel();
    }

    /**
     * @deprecated
     */
    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->catch($this->wrapContext($onRejected));
    }

    /**
     * @deprecated
     */
    public function always(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->finally($this->wrapContext($onFulfilledOrRejected));
    }

    /**
     * @template TParam of mixed
     * @template TReturn of mixed
     * @param (callable(TParam): TReturn)|null $callback
     * @return ($callback is null ? null : (callable(TParam): TReturn))
     */
    private function wrapContext(?callable $callback): ?callable
    {
        if ($callback === null) {
            return null;
        }

        return function (mixed $value = null) use ($callback): mixed {
            Workflow::setCurrentContext($this->context);
            return $callback($value);
        };
    }
}
