<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;

/**
 * Universal decorator for workflow proxy objects.
 *
 * Wraps any proxy (ActivityProxy, ChildWorkflowProxy, ContinueAsNewProxy,
 * ExternalWorkflowProxy) and auto-suspends the Fiber when the proxied
 * method returns a PromiseInterface.
 *
 * @experimental
 * @internal
 */
final class FiberProxy
{
    public function __construct(
        private readonly object $inner,
    ) {}

    public function __call(string $method, array $args): mixed
    {
        $result = $this->inner->__call($method, $args);

        if ($result instanceof PromiseInterface) {
            return FiberHelper::await($result);
        }

        return $result;
    }
}
