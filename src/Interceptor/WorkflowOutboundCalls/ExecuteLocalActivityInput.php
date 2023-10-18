<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use Temporal\Activity\LocalActivityOptions;
use Temporal\DataConverter\Type;

/**
 * @psalm-immutable
 */
final class ExecuteLocalActivityInput
{
    /**
     * @param non-empty-string $type
     * @param \ReflectionMethod|null $method Not null if activity class is known.
     *
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly string $type,
        public readonly array $args,
        public readonly ?LocalActivityOptions $options,
        public readonly null|Type|string|\ReflectionClass|\ReflectionType $returnType,
        public readonly ?\ReflectionMethod $method = null,
    ) {
    }

    /**
     * @param non-empty-string|null $type
     */
    public function with(
        ?string $type = null,
        ?array $args = null,
        ?LocalActivityOptions $options = null,
        null|Type|string|\ReflectionClass|\ReflectionType $returnType = null,
        ?\ReflectionMethod $method = null,
    ): self {
        return new self(
            $type ?? $this->type,
            $args ?? $this->args,
            $options ?? $this->options,
            $returnType ?? $this->returnType,
            $method ?? $this->method,
        );
    }
}
