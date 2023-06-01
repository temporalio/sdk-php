<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use JetBrains\PhpStorm\Immutable;
use Temporal\Workflow\ContinueAsNewOptions;

/**
 * @psalm-immutable
 */
#[Immutable]
final class ContinueAsNewInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public string $type,
        #[Immutable]
        public array $args = [],
        #[Immutable]
        public ?ContinueAsNewOptions $options = null,
    ) {
    }

    public function with(
        ?string $type = null,
        ?array $args = null,
        ?ContinueAsNewOptions $options = null,
    ): self {
        return new self(
            $type ?? $this->type,
            $args ?? $this->args,
            $options ?? $this->options,
        );
    }
}
