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

interface ChildWorkflowStubInterface
{
    /**
     * @return PromiseInterface<WorkflowExecution>
     * @throws \LogicException
     */
    public function getExecution(): PromiseInterface;

    /**
     * @return string
     */
    public function getChildWorkflowType(): string;

    /**
     * @return ChildWorkflowOptions
     */
    public function getOptions(): ChildWorkflowOptions;

    /**
     * @param array $args
     * @param \ReflectionType|null $returnType
     * @return PromiseInterface
     */
    public function execute(array $args = [], \ReflectionType $returnType = null): PromiseInterface;

    /**
     * @param string $name
     * @param array $args
     * @return PromiseInterface
     * @throws \LogicException
     */
    public function signal(string $name, array $args = []): PromiseInterface;
}
