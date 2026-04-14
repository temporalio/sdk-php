<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\Type;

interface NexusOperationStubInterface
{
    public function getOptions(): NexusOperationOptions;

    /**
     * Execute a Nexus operation.
     *
     * @param non-empty-string $operation Operation name
     * @param array $args Operation arguments
     * @param Type|string|\ReflectionClass|\ReflectionType|null $returnType Expected return type
     */
    public function execute(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): PromiseInterface;
}
