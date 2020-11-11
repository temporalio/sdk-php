<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Internal\Prototype\Reader;

use Temporal\Client\Internal\Prototype\WorkflowPrototype;
use Temporal\Client\Internal\Prototype\WorkflowPrototypeInterface;
use Temporal\Client\Workflow\Meta\QueryMethod;
use Temporal\Client\Workflow\Meta\SignalMethod;
use Temporal\Client\Workflow\Meta\WorkflowInterface;
use Temporal\Client\Workflow\Meta\WorkflowMethod;

/**
 * @template-implements ReaderInterface<WorkflowPrototypeInterface>
 */
class WorkflowReader extends Reader
{
    /**
     * @param string $class
     * @return WorkflowPrototypeInterface[]
     * @throws \ReflectionException
     */
    public function fromClass(string $class): iterable
    {
        $declarations = [];
        $reflection = new \ReflectionClass($class);
        $interface = $this->getWorkflowInterface($reflection);

        foreach ($this->annotatedMethods($reflection, WorkflowMethod::class) as $method => $handler) {
            $method->name ??= $handler->getName();

            $declarations[] = new WorkflowPrototype($interface, $method, $handler);
        }

        foreach ($this->annotatedMethods($reflection, SignalMethod::class) as $signal => $handler) {
            $signal->name ??= $handler->getName();

            foreach ($declarations as $declaration) {
                $declaration->addSignalHandler($signal, $handler);
            }
        }

        foreach ($this->annotatedMethods($reflection, QueryMethod::class) as $query => $handler) {
            $query->name ??= $handler->getName();

            foreach ($declarations as $declaration) {
                $declaration->addQueryHandler($query, $handler);
            }
        }

        return $declarations;
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
