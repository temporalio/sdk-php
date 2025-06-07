<?php

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

/**
 * @internal
 */
final class QueryDefinition
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public readonly string $name,
        public readonly mixed $returnType,
        public readonly \ReflectionMethod $method,
        public readonly string $description,
    ) {}
}
