<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @experimental
 */
interface FiberExternalWorkflowStubInterface
{
    public function getExecution(): WorkflowExecution;

    /**
     * @param non-empty-string $name
     * @param list<mixed> $args
     */
    public function signal(string $name, array $args = []): void;

    public function cancel(): void;

    /**
     * @param non-empty-string $name
     * @param list<mixed> $args
     */
    public function signalAsync(string $name, array $args = []): PromiseInterface;

    public function cancelAsync(): PromiseInterface;
}
