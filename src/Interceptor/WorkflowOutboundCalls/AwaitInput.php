<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use React\Promise\PromiseInterface;

/**
 * @psalm-immutable
 */
final class AwaitInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     *
     * @param array<callable|PromiseInterface> $conditions
     */
    public function __construct(
        public readonly array $conditions,
    ) {
    }

    /**
     * @param array<callable|PromiseInterface> $conditions
     */
    public function with(
        ?array $conditions = null,
    ): self {
        return new self(
            $conditions ?? $this->conditions,
        );
    }
}
