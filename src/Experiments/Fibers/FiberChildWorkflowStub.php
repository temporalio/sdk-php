<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * Fiber-friendly decorator for {@see ChildWorkflowStubInterface}.
 *
 * Wraps all PromiseInterface-returning methods with {@see FiberHelper::await()},
 * so the caller gets resolved values instead of promises.
 *
 * @experimental
 * @internal
 */
final class FiberChildWorkflowStub
{
    public function __construct(
        private readonly ChildWorkflowStubInterface $inner,
    ) {}

    /**
     * @return WorkflowExecution
     */
    public function getExecution(): mixed
    {
        return FiberHelper::await($this->inner->getExecution());
    }

    public function getChildWorkflowType(): string
    {
        return $this->inner->getChildWorkflowType();
    }

    public function getOptions(): ChildWorkflowOptions
    {
        return $this->inner->getOptions();
    }

    /**
     * Start the child workflow and return the {@see WorkflowExecution}.
     */
    public function start(mixed ...$args): mixed
    {
        return FiberHelper::await($this->inner->start(...$args));
    }

    /**
     * Get the result of the child workflow.
     */
    public function getResult(mixed $returnType = null): mixed
    {
        return FiberHelper::await($this->inner->getResult($returnType));
    }

    /**
     * Execute (start + wait for result) the child workflow.
     */
    public function execute(array $args = [], mixed $returnType = null): mixed
    {
        return FiberHelper::await($this->inner->execute($args, $returnType));
    }

    /**
     * Signal the child workflow.
     */
    public function signal(string $name, array $args = []): mixed
    {
        return FiberHelper::await($this->inner->signal($name, $args));
    }
}
