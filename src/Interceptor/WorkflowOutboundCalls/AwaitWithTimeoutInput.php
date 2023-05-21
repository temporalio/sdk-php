<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use DateInterval;
use JetBrains\PhpStorm\Immutable;
use React\Promise\PromiseInterface;

/**
 * @psalm-immutable
 */
#[Immutable]
final class AwaitWithTimeoutInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     *
     * @param array<callable|PromiseInterface> $conditions
     */
    public function __construct(
        #[Immutable]
        public DateInterval $interval,
        #[Immutable]
        public array $conditions,
    ) {
    }

    /**
     * @param array<callable|PromiseInterface> $conditions
     */
    public function with(
        ?DateInterval $interval = null,
        ?array $conditions = null,
    ): self {
        return new self(
            $interval ?? $this->interval,
            $conditions ?? $this->conditions,
        );
    }
}
