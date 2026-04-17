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
     * Kicks off the operation and returns a promise resolving to the
     * decoded result (or rejecting on handler/operation failure). Sugar
     * over {@see self::start()} when the workflow does not need the
     * intermediate {@see NexusOperationHandle}.
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

    /**
     * Start a Nexus operation and return a {@see NexusOperationHandle}
     * that can be awaited later. Mirrors Java's
     * `NexusOperationStub.start(...)`.
     *
     * Implementations that do not support the split are free to implement
     * this via `execute()` — the handle will simply resolve as soon as
     * the operation completes.
     *
     * @param non-empty-string $operation
     */
    public function start(
        string $operation,
        array $args = [],
        Type|string|\ReflectionClass|\ReflectionType|null $returnType = null,
    ): NexusOperationHandle;
}
