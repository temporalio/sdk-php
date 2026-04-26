<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\Type;

interface NexusOperationStubInterface
{
    public function getOptions(): NexusOperationOptions;

    /**
     * Sugar over {@see self::start()}->getResult().
     *
     * @param non-empty-string $operation
     * @param array<string, string> $nexusHeaders Raw-string headers carried on the Nexus wire.
     */
    public function execute(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        array $nexusHeaders = [],
    ): PromiseInterface;

    /**
     * Start a Nexus operation and return a {@see NexusOperationHandle}.
     *
     * @param non-empty-string $operation
     * @param array<string, string> $nexusHeaders
     */
    public function start(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
        array $nexusHeaders = [],
    ): NexusOperationHandle;
}
