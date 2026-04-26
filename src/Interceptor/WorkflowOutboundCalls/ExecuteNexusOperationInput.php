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
     * @param array<string, string> $nexusHeaders raw-string headers forwarded
     *        on the Nexus operation wire (separate from the Temporal `Header`
     *        that carries payload-typed values).
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
        public readonly array $nexusHeaders = [],
    ) {}

    public function with(
        ?string $service = null,
        ?string $operation = null,
        ?array $args = null,
        ?NexusOperationOptions $options = null,
        null|Type|string|\ReflectionClass|\ReflectionType $returnType = null,
        ?array $nexusHeaders = null,
    ): self {
        /** @psalm-suppress ArgumentTypeCoercion non-empty contract preserved by ?? fallback */
        return new self(
            $service ?? $this->service,
            $operation ?? $this->operation,
            $args ?? $this->args,
            $options ?? $this->options,
            $returnType ?? $this->returnType,
            $nexusHeaders ?? $this->nexusHeaders,
        );
    }
}
