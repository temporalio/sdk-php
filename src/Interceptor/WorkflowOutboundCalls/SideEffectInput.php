<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use Closure;
use JetBrains\PhpStorm\Immutable;

/**
 * @psalm-immutable
 */
#[Immutable]
final class SideEffectInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public Closure $callable,
    ) {
    }

    public function with(
        ?Closure $callable = null,
    ): self {
        return new self(
            $callable ?? $this->callable,
        );
    }
}
