<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use Temporal\Workflow\ExternalWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * Fiber-friendly decorator for {@see ExternalWorkflowStubInterface}.
 *
 * Wraps all PromiseInterface-returning methods with {@see FiberHelper::await()},
 * so the caller gets resolved values instead of promises.
 *
 * @experimental
 * @internal
 */
final class FiberExternalWorkflowStub
{
    public function __construct(
        private readonly ExternalWorkflowStubInterface $inner,
    ) {}

    public function getExecution(): WorkflowExecution
    {
        return $this->inner->getExecution();
    }

    public function signal(string $name, array $args = []): mixed
    {
        return FiberHelper::await($this->inner->signal($name, $args));
    }

    public function cancel(): mixed
    {
        return FiberHelper::await($this->inner->cancel());
    }
}
