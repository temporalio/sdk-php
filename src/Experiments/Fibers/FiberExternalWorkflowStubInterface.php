<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\WorkflowExecution;

interface FiberExternalWorkflowStubInterface
{
    public function getExecution(): WorkflowExecution;

    public function signal(string $name, array $args = []): void;

    public function cancel(): void;

    public function signalAsync(string $name, array $args = []): PromiseInterface;

    public function cancelAsync(): PromiseInterface;
}
