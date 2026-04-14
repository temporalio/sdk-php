<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use Temporal\DataConverter\Type;
use Temporal\Workflow\NexusOperationOptions;

/**
 * @psalm-immutable
 */
final class ExecuteNexusOperationInput
{
    /**
     * @param non-empty-string $service
     * @param non-empty-string $operation
     *
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly string $service,
        public readonly string $operation,
        public readonly array $args,
        public readonly NexusOperationOptions $options,
        public readonly null|Type|string|\ReflectionClass|\ReflectionType $returnType,
    ) {}

    public function with(
        ?string $service = null,
        ?string $operation = null,
        ?array $args = null,
        ?NexusOperationOptions $options = null,
        null|Type|string|\ReflectionClass|\ReflectionType $returnType = null,
    ): self {
        return new self(
            $service ?? $this->service,
            $operation ?? $this->operation,
            $args ?? $this->args,
            $options ?? $this->options,
            $returnType ?? $this->returnType,
        );
    }
}
