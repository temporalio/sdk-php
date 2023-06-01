<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use JetBrains\PhpStorm\Immutable;
use Temporal\Workflow\ChildWorkflowOptions;

/**
 * @psalm-immutable
 */
#[Immutable]
final class ExecuteChildWorkflowInput
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
        public ?ChildWorkflowOptions $options = null,
        #[Immutable]
        public mixed $returnType = null,
    ) {
    }

    public function with(
        ?string $type = null,
        ?array $args = null,
        ?ChildWorkflowOptions $options = null,
        mixed $returnType = null,
    ): self {
        return new self(
            $type ?? $this->type,
            $args ?? $this->args,
            $options ?? $this->options,
            $returnType ?? $this->returnType
        );
    }
}
