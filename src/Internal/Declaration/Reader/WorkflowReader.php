<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Declaration\Reader;

use ReflectionFunctionAbstract as ReflectionFunction;
use Temporal\Client\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowInterface;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

/**
 * @template-extends Reader<WorkflowPrototype>
 */
class WorkflowReader extends Reader
{
    /**
     * {@inheritDoc}
     */
    public function fromClass(string $class): iterable
    {
        $declarations = [];
        $reflection = new \ReflectionClass($class);

        //$interface = $this->getWorkflowInterface($reflection);

        foreach ($this->annotatedMethods($reflection, WorkflowMethod::class) as $method => $handler) {
            $name = $this->createWorkflowName($handler, $method);

            $declarations[] = new WorkflowPrototype($name, $handler, $reflection);
        }

        foreach ($this->annotatedMethods($reflection, SignalMethod::class) as $signal => $handler) {
            $name = $this->createWorkflowSignalName($handler, $signal);

            foreach ($declarations as $declaration) {
                $declaration->addSignalHandler($name, $handler);
            }
        }

        foreach ($this->annotatedMethods($reflection, QueryMethod::class) as $query => $handler) {
            $name = $this->createWorkflowQueryName($handler, $query);

            foreach ($declarations as $declaration) {
                $declaration->addQueryHandler($name, $handler);
            }
        }

        return $declarations;
    }

    /**
     * @param ReflectionFunction $fun
     * @param WorkflowMethod $method
     * @return string
     */
    private function createWorkflowName(ReflectionFunction $fun, WorkflowMethod $method): string
    {
        return $method->name ?? $fun->getName();
    }

    /**
     * @param ReflectionFunction $fun
     * @param QueryMethod $method
     * @return string
     */
    private function createWorkflowQueryName(ReflectionFunction $fun, QueryMethod $method): string
    {
        return $method->name ?? $fun->getName();
    }

    /**
     * @param ReflectionFunction $fun
     * @param SignalMethod $method
     * @return string
     */
    private function createWorkflowSignalName(ReflectionFunction $fun, SignalMethod $method): string
    {
        return $method->name ?? $fun->getName();
    }

    /**
     * @param \ReflectionClass $class
     * @return WorkflowInterface
     */
    private function getWorkflowInterface(\ReflectionClass $class): WorkflowInterface
    {
        $attributes = $this->reader->getClassMetadata($class, WorkflowInterface::class);

        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($attributes as $attribute) {
            return $attribute;
        }

        return new WorkflowInterface();
    }
}
