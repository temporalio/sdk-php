<?php

declare(strict_types=1);

namespace Temporal\Experiments\Fibers;

use React\Promise\PromiseInterface;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowExecution;

/**
 * @experimental
 */
interface FiberChildWorkflowStubInterface
{
    public function getExecution(): WorkflowExecution;

    public function getChildWorkflowType(): string;

    public function getOptions(): ChildWorkflowOptions;

    public function start(mixed ...$args): WorkflowExecution;

    public function getResult(mixed $returnType = null): mixed;

    /**
     * @param list<mixed> $args
     */
    public function execute(array $args = [], mixed $returnType = null): mixed;

    /**
     * @param non-empty-string $name
     * @param list<mixed> $args
     */
    public function signal(string $name, array $args = []): void;

    public function startAsync(mixed ...$args): PromiseInterface;

    public function getResultAsync(mixed $returnType = null): PromiseInterface;

    /**
     * @param list<mixed> $args
     */
    public function executeAsync(array $args = [], mixed $returnType = null): PromiseInterface;

    /**
     * @param non-empty-string $name
     * @param list<mixed> $args
     */
    public function signalAsync(string $name, array $args = []): PromiseInterface;
}
