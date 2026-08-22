<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\DataConverter\Type;

/**
 * @psalm-import-type TType from Type
 */
interface ChildWorkflowStubInterface
{
    /**
     * @throws \LogicException
     */
    public function getExecution(): WorkflowExecution;

    /**
     * @internal
     * @return PromiseInterface<WorkflowExecution>
     * @throws \LogicException
     */
    public function getExecutionAsync(): PromiseInterface;

    public function getChildWorkflowType(): string;

    public function getOptions(): ChildWorkflowOptions;

    /**
     * @param TType $returnType
     */
    public function execute(array $args = [], $returnType = null): mixed;

    /**
     * @param TType $returnType
     * @return PromiseInterface<mixed>
     */
    public function executeAsync(array $args = [], $returnType = null): PromiseInterface;

    /**
     * @param mixed ...$args
     */
    public function start(...$args): WorkflowExecution;

    /**
     * @param mixed ...$args
     * @internal
     * @return PromiseInterface<WorkflowExecution>
     */
    public function startAsync(...$args): PromiseInterface;

    /**
     * @param TType $returnType
     */
    public function getResult($returnType = null): mixed;

    /**
     * @param TType $returnType
     * @internal
     * @return PromiseInterface<mixed>
     */
    public function getResultAsync($returnType = null): PromiseInterface;

    /**
     * @param non-empty-string $name
     *
     * @throws \LogicException
     */
    public function signal(string $name, array $args = []): void;

    /**
     * @param non-empty-string $name
     * @internal
     * @return PromiseInterface<mixed>
     *
     * @throws \LogicException
     */
    public function signalAsync(string $name, array $args = []): PromiseInterface;
}
