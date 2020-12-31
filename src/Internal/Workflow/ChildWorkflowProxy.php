<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

use React\Promise\PromiseInterface;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Internal\Repository\RepositoryInterface;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\WorkflowContextInterface;

/**
 * @internal ChildWorkflowProxy is an internal library class, please do not use it in your code.
 * @psalm-internal Temporal\Client
 *
 * @psalm-template Workflow of object
 */
class ChildWorkflowProxy
{
    /**
     * @var WorkflowPrototype[]
     */
    private array $workflows;

    /**
     * @var ChildWorkflowOptions
     */
    private ChildWorkflowOptions $options;

    /**
     * @var WorkflowContextInterface
     */
    private WorkflowContextInterface $context;

    /**
     * @param class-string<Workflow> $class
     * @param ChildWorkflowOptions $options
     * @param WorkflowContextInterface $context
     * @param RepositoryInterface<WorkflowPrototype> $workflows
     */
    public function __construct(
        string $class,
        ChildWorkflowOptions $options,
        WorkflowContextInterface $context,
        RepositoryInterface $workflows
    ) {
        $this->options = $options;
        $this->context = $context;

        $this->workflows = [
            ...$this->filterWorkflows($workflows, $class),
        ];
    }

    /**
     * @param WorkflowPrototype[] $workflows
     * @param string $class
     * @return \Traversable
     */
    private function filterWorkflows(iterable $workflows, string $class): \Traversable
    {
        foreach ($workflows as $workflow) {
            if ($this->matchClass($workflow, $class)) {
                yield $workflow;
            }
        }
    }

    /**
     * @param WorkflowPrototype $prototype
     * @param string $needle
     * @return bool
     */
    private function matchClass(WorkflowPrototype $prototype, string $needle): bool
    {
        $reflection = $prototype->getClass();

        return $reflection && $reflection->getName() === \trim($needle, '\\');
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return PromiseInterface
     */
    public function __call(string $method, array $arguments = []): PromiseInterface
    {
        return $this->call($method, $arguments);
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return PromiseInterface
     */
    public function call(string $method, array $arguments = []): PromiseInterface
    {
        $activity = $this->findWorkflowPrototype($method);

        $method = $activity ? $activity->getId() : $method;

        return $this->context->executeChildWorkflow($method, $arguments, $this->options);
    }

    /**
     * @param string $name
     * @return WorkflowPrototype|null
     */
    private function findWorkflowPrototype(string $name): ?WorkflowPrototype
    {
        foreach ($this->workflows as $workflow) {
            if ($this->matchMethod($workflow, $name)) {
                return $workflow;
            }
        }

        return null;
    }

    /**
     * @param WorkflowPrototype $prototype
     * @param string $needle
     * @return bool
     */
    private function matchMethod(WorkflowPrototype $prototype, string $needle): bool
    {
        $handler = $prototype->getHandler();

        return $handler->getName() === $needle;
    }
}
