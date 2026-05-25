<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\CancellationScopeInterface;

/**
 * @experimental
 */
final class FiberScope implements CancellationScopeInterface
{
    public function __construct(
        private readonly CancellationScopeInterface $inner,
    ) {}

    public function isDetached(): bool
    {
        return $this->inner->isDetached();
    }

    public function isCancelled(): bool
    {
        return $this->inner->isCancelled();
    }

    public function onCancel(callable $then): self
    {
        $this->inner->onCancel($then);
        return $this;
    }

    public function cancel(): void
    {
        $this->inner->cancel();
    }

    public function join(): mixed
    {
        return FiberHelper::await($this->inner);
    }

    public function then(
        ?callable $onFulfilled = null,
        ?callable $onRejected = null,
    ): PromiseInterface {
        return $this->inner->then($onFulfilled, $onRejected);
    }

    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->inner->catch($onRejected);
    }

    public function finally(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->inner->finally($onFulfilledOrRejected);
    }

    public function otherwise(callable $onRejected): PromiseInterface
    {
        return $this->inner->otherwise($onRejected);
    }

    public function always(callable $onFulfilledOrRejected): PromiseInterface
    {
        return $this->inner->always($onFulfilledOrRejected);
    }
}
