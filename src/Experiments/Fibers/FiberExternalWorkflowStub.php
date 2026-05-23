<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @experimental
 * @internal
 */
final class FiberExternalWorkflowStub implements FiberExternalWorkflowStubInterface
{
    public function __construct(
        private readonly ExternalWorkflowStubInterface $inner,
    ) {}

    public function getExecution(): WorkflowExecution
    {
        return $this->inner->getExecution();
    }

    public function signal(string $name, array $args = []): void
    {
        FiberHelper::await($this->inner->signal($name, $args));
    }

    public function cancel(): void
    {
        FiberHelper::await($this->inner->cancel());
    }

    public function signalAsync(string $name, array $args = []): PromiseInterface
    {
        return $this->inner->signal($name, $args);
    }

    public function cancelAsync(): PromiseInterface
    {
        return $this->inner->cancel();
    }
}
