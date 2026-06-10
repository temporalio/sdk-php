<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
     * @param array<string, string> $nexusHeaders raw-string headers forwarded
     *        on the Nexus operation wire (separate from the Temporal `Header`
     *        that carries payload-typed values).
     *
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $service,
        public readonly string $operation,
        public readonly array $args,
        public readonly NexusOperationOptions $options,
        public readonly null|Type|string|\ReflectionClass|\ReflectionType $returnType,
        public readonly array $nexusHeaders = [],
    ) {
        if ($endpoint === '') {
            throw new \InvalidArgumentException('$endpoint must be a non-empty string.');
        }
        if ($service === '') {
            throw new \InvalidArgumentException('$service must be a non-empty string.');
        }
        if ($operation === '') {
            throw new \InvalidArgumentException('$operation must be a non-empty string.');
        }
    }

    public function with(
        ?string $endpoint = null,
        ?string $service = null,
        ?string $operation = null,
        ?array $args = null,
        ?NexusOperationOptions $options = null,
        null|Type|string|\ReflectionClass|\ReflectionType $returnType = null,
        ?array $nexusHeaders = null,
    ): self {
        return new self(
            $endpoint ?? $this->endpoint,
            $service ?? $this->service,
            $operation ?? $this->operation,
            $args ?? $this->args,
            $options ?? $this->options,
            $returnType ?? $this->returnType,
            $nexusHeaders ?? $this->nexusHeaders,
        );
    }
}
