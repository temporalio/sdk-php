<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use JetBrains\PhpStorm\Immutable;

/**
 * @psalm-immutable
 */
#[Immutable]
final class UpsertSearchAttributesInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public array $searchAttributes,
    ) {
    }

    public function with(
        ?array $searchAttributes = null,
    ): self {
        return new self(
            $searchAttributes ?? $this->searchAttributes,
        );
    }
}
