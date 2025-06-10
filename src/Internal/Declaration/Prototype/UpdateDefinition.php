<?php

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Workflow\HandlerUnfinishedPolicy;

/**
 * @internal
 */
final class UpdateDefinition
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly HandlerUnfinishedPolicy $policy,
        public readonly mixed $returnType,
        public readonly \ReflectionMethod $method,
        public readonly ?\ReflectionMethod $validator,
    ) {}
}
