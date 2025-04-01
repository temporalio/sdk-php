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
use Temporal\Internal\Transport\CompletableResultInterface;

interface ChildWorkflowStubInterface
{
    /**
     * @throws \LogicException
     */
    public function getExecution(): PromiseInterface;

    public function getChildWorkflowType(): string;

    public function getOptions(): ChildWorkflowOptions;

    /**
     * @param Type|string|\ReflectionType|\ReflectionClass|null $returnType
     *
     * @return CompletableResultInterface
     */
    public function execute(array $args = [], $returnType = null): PromiseInterface;

    /**
     * @param array $args
     *
     * @return CompletableResultInterface<WorkflowExecution>
     */
    public function start(...$args): PromiseInterface;

    public function getResult($returnType = null): PromiseInterface;

    /**
     * @param non-empty-string $name
     *
     * @return CompletableResultInterface
     * @throws \LogicException
     */
    public function signal(string $name, array $args = []): PromiseInterface;
}
