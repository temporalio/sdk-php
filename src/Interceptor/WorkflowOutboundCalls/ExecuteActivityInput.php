<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use JetBrains\PhpStorm\Immutable;
use Temporal\Activity\ActivityOptions;
use Temporal\DataConverter\Type;

/**
 * @psalm-immutable
 */
#[Immutable]
final class ExecuteActivityInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public string $type,
        #[Immutable]
        public array $args,
        #[Immutable]
        public ?ActivityOptions $options,
        #[Immutable]
        public null|Type|string|\ReflectionClass|\ReflectionType $returnType,
    ) {
    }

    public function with(
        ?string $type = null,
        ?array $args = null,
        ?ActivityOptions $options = null,
        null|Type|string|\ReflectionClass|\ReflectionType $returnType = null,
    ): self {
        return new self(
            $type ?? $this->type,
            $args ?? $this->args,
            $options ?? $this->options,
            $returnType ?? $this->returnType
        );
    }
}
