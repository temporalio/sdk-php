<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use JetBrains\PhpStorm\Immutable;
use DateInterval;

/**
 * @psalm-immutable
 */
#[Immutable]
final class TimerInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public DateInterval $interval,
    ) {
    }

    public function with(
        ?DateInterval $interval = null,
    ): self {
        return new self(
            $interval ?? $this->interval,
        );
    }
}
