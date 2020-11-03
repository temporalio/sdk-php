<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Future;

use React\Promise\CancellablePromiseInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Temporal\Client\Worker\Loop;

class Future implements FutureInterface
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
     * @var CancellablePromiseInterface
     */
    private CancellablePromiseInterface $promise;

    /**
     * @var Deferred
     */
    private Deferred $deferred;

    /**
     * @param CancellablePromiseInterface $promise
     */
    public function __construct(CancellablePromiseInterface $promise)
    {
        $this->deferred = new Deferred(function () use ($promise) {
            $promise->cancel();
        });

        /** @var CancellablePromiseInterface $current */
        $current = $promise->then(
            \Closure::fromCallable([$this, 'onFulfilled']),
            \Closure::fromCallable([$this, 'onRejected']),
        );

        $this->promise = $current;
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
    public function cancel(): void
    {
        $this->promise->cancel();
    }

    /**
     * {@inheritDoc}
     */
    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null,
        callable $onProgress = null
    ): FutureInterface {
        /** @var CancellablePromiseInterface $promise */
        $promise = $this->promise()
            ->then($onFulfilled, $onRejected, $onProgress)
        ;

        return new Future($promise);
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

        Loop::onTick(function () {
            $this->deferred->resolve($this->value);
        }, Loop::ON_CALLBACK);
    }

    /**
     * @param \Throwable $e
     */
    private function onRejected(\Throwable $e): void
    {
        $this->resolved = true;

        Loop::onTick(function () use ($e) {
            $this->deferred->reject($e);
        }, Loop::ON_CALLBACK);
    }
}
