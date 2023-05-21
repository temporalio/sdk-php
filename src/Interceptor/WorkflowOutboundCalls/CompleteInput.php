<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use JetBrains\PhpStorm\Immutable;

/**
 * @psalm-immutable
 */
#[Immutable]
final class CompleteInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public ?array $result,
        #[Immutable]
        public ?\Throwable $failure,
    ) {
    }

    public function with(
        ?array $result = null,
        ?\Throwable $failure = null,
    ): self {
        return new self(
            $result ?? $this->result,
            $failure ?? $this->failure,
        );
    }
}
