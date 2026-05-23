<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @experimental
 * @internal
 */
final class FiberChildWorkflowStub implements FiberChildWorkflowStubInterface
{
    public function __construct(
        private readonly ChildWorkflowStubInterface $inner,
    ) {}

    public function getExecution(): WorkflowExecution
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

    public function start(mixed ...$args): WorkflowExecution
    {
        return FiberHelper::await($this->inner->start(...$args));
    }

    public function getResult(mixed $returnType = null): mixed
    {
        return FiberHelper::await($this->inner->getResult($returnType));
    }

    public function execute(array $args = [], mixed $returnType = null): mixed
    {
        return FiberHelper::await($this->inner->execute($args, $returnType));
    }

    public function signal(string $name, array $args = []): void
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        FiberHelper::await($this->inner->signal($name, $args));
    }

    public function startAsync(mixed ...$args): PromiseInterface
    {
        return $this->inner->start(...$args);
    }

    public function getResultAsync(mixed $returnType = null): PromiseInterface
    {
        return $this->inner->getResult($returnType);
    }

    public function executeAsync(array $args = [], mixed $returnType = null): PromiseInterface
    {
        return $this->inner->execute($args, $returnType);
    }

    public function signalAsync(string $name, array $args = []): PromiseInterface
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->inner->signal($name, $args);
    }
}
