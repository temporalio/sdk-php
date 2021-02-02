<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport;

use React\Promise\CancellablePromiseInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Worker\LoopInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowContextInterface;

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

        /** @var CancellablePromiseInterface $current */
        $this->promise = $promise->then(
            \Closure::fromCallable([$this, 'onFulfilled']),
            \Closure::fromCallable([$this, 'onRejected']),
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
     * {@inheritDoc}
     */
    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null,
        callable $onProgress = null
    ): PromiseInterface {
        Workflow::setCurrentContext($this->context);

        /** @var CancellablePromiseInterface $promise */
        $promise = $this->promise()
            ->then($onFulfilled, $onRejected, $onProgress);

        return $promise;
        //return new Future($promise, $this->worker);
    }

    /**
     * @return PromiseInterface
     */
    public function promise(): PromiseInterface
    {
        return $this->deferred->promise();
    }

    /**
     * @param mixed $result
     */
    private function onFulfilled($result): void
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
}
