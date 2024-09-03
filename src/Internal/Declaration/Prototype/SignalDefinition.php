<?php

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Workflow\HandlerUnfinishedPolicy;

/**
 * @internal
 */
final class SignalDefinition
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public readonly string $name,
        public readonly HandlerUnfinishedPolicy $policy,
        public readonly \ReflectionMethod $method,
    ) {}
}
