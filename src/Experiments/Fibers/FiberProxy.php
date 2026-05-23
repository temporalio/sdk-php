<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;

/**
 * @template T of object
 * @mixin T
 *
 * @experimental
 * @internal
 */
final class FiberProxy
{
    /**
     * @param T $inner
     */
    public function __construct(
        private readonly object $inner,
    ) {}

    public function __call(string $method, array $args): mixed
    {
        $result = $this->inner->__call($method, $args);

        if ($result instanceof PromiseInterface) {
            return FiberHelper::await($result);
        }

        throw new \LogicException(\sprintf(
            'FiberProxy expects the inner proxy to return a PromiseInterface; got %s.',
            \get_debug_type($result),
        ));
    }
}
