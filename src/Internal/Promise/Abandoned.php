<?php

declare(strict_types=1);

namespace Temporal\Internal\Promise;

use React\Promise\PromiseInterface;

/**
 * @internal
 * @psalm-internal Temporal\Internal
 * @implements PromiseInterface<mixed>
 */
final class Abandoned implements PromiseInterface
{
    public function __construct(
        private readonly bool $forward = false,
    ) {}

    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): PromiseInterface
    {
        if (!$this->forward) {
            return new self(forward: true);
        }

        if ($onFulfilled !== null) {
            $onFulfilled(new self());
        }

        return new self();
    }

    public function catch(callable $onRejected): PromiseInterface
    {
        return $this;
    }

    public function finally(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this;
    }

    public function cancel(): void {}

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this;
    }

    public function always(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this;
    }
}
