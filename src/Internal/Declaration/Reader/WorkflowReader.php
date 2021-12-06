<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Temporal\Common\CronSchedule;
use Temporal\Common\MethodRetry;
use Temporal\Internal\Declaration\Graph\ClassNode;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * @template-extends Reader<WorkflowPrototype>
 */
class WorkflowReader extends Reader
{
    /**
     * @var string
     */
    private const ERROR_WORKFLOW_INTERFACE_NOT_FOUND =
        'Workflow class %s or one of his parents (i.e. class, interface or trait) ' .
        'must contain #[%s] attribute'
    ;

    /**
     * @var string
     */
    private const ERROR_HANDLER_NOT_FOUND =
        'Can not find workflow handler, because class %s has no method marked with #[%s] attribute'
    ;

    /**
     * @var string
     */
    private const ERROR_HANDLER_DUPLICATE =
        'Workflow class %s must contain only one handler marked by #[%s] attribute, but %d has been found'
    ;

    /**
     * @var string
     */
    private const ERROR_HANDLER_VISIBILITY =
        'A Workflow handler method can only be a public non-static method, ' .
        'but %s::%s() does not meet these criteria'
    ;

    /**
     * @var string
     */
    private const ERROR_COMMON_METHOD_VISIBILITY =
        'A Workflow %s handler method can only be a public non-static method, ' .
        'but %s::%s() does not meet these criteria'
    ;

    /**
     * @param string $class
     * @return WorkflowPrototype
     * @throws \ReflectionException
     */
    public function fromClass(string $class): WorkflowPrototype
    {
        $reflection = new \ReflectionClass($class);
        $graph = new ClassNode($reflection);

        $this->assertWorkflowInterface($graph);

        $prototypes = \iterator_to_array($this->getWorkflowPrototypes($graph), false);

        switch (\count($prototypes)) {
            case 0:
                $message = \sprintf(self::ERROR_HANDLER_NOT_FOUND, $graph, WorkflowMethod::class);
                throw new \LogicException($message);

            case 1:
                return $this->withSignalsAndQueries($graph, \reset($prototypes));

            default:
                $message = \sprintf(self::ERROR_HANDLER_DUPLICATE, $graph, WorkflowMethod::class, \count($prototypes));
                throw new \LogicException($message);
        }
    }

    public function fromObject(object $object): WorkflowPrototype
    {
        return $this->fromClass(get_class($object));
    }

    /**
     * @param ClassNode $graph
     * @return \Traversable<ActivityPrototype>
     * @throws \ReflectionException
     */
    protected function getWorkflowPrototypes(ClassNode $graph): \Traversable
    {
        $class = $graph->getReflection();

        foreach ($class->getMethods() as $reflection) {
            if (!$this->isValidMethod($reflection)) {
                continue;
            }

            if ($prototype = $this->getPrototype($graph, $reflection)) {
                yield $prototype;
            }
        }
    }

    /**
     * @param ClassNode $graph
     * @param WorkflowPrototype $prototype
     * @return WorkflowPrototype
     * @throws \ReflectionException
     */
    private function withSignalsAndQueries(ClassNode $graph, WorkflowPrototype $prototype): WorkflowPrototype
    {
        $class = $graph->getReflection();

        foreach ($class->getMethods() as $ctx) {
            $contextClass = $ctx->getDeclaringClass();

            /** @var SignalMethod|null $signal */
            $signal = $this->getAttributedMethod($graph, $ctx, SignalMethod::class);

            if ($signal !== null) {
                // Validation
                if (!$this->isValidMethod($ctx)) {
                    throw new \LogicException(
                        \vsprintf(self::ERROR_COMMON_METHOD_VISIBILITY, [
                            'signal',
                            $contextClass->getName(),
                            $ctx->getName(),
                        ])
                    );
                }

                $prototype->addSignalHandler(
                    $signal->name ?? $ctx->getName(),
                    $ctx
                );
            }

            /** @var QueryMethod|null $query */
            $query = $this->getAttributedMethod($graph, $ctx, QueryMethod::class);

            if ($query !== null) {
                // Validation
                if (!$this->isValidMethod($ctx)) {
                    throw new \LogicException(
                        \vsprintf(self::ERROR_COMMON_METHOD_VISIBILITY, [
                            'query',
                            $contextClass->getName(),
                            $ctx->getName(),
                        ])
                    );
                }

                $prototype->addQueryHandler(
                    $query->name ?? $ctx->getName(),
                    $ctx
                );
            }
        }

        return $prototype;
    }

    /**
     * @param ClassNode $graph
     */
    private function assertWorkflowInterface(ClassNode $graph): void
    {
        foreach ($graph as $edge) {
            foreach ($edge as $node) {
                $attribute = $this->reader->firstClassMetadata($node->getReflection(), WorkflowInterface::class);

                if ($attribute !== null) {
                    return;
                }
            }
        }

        throw new \LogicException(
            \sprintf(self::ERROR_WORKFLOW_INTERFACE_NOT_FOUND, $graph, WorkflowInterface::class)
        );
    }

    /**
     * @param ClassNode $graph
     * @param \ReflectionMethod $handler
     * @param string $name
     * @return object|null
     * @throws \ReflectionException
     */
    private function getAttributedMethod(ClassNode $graph, \ReflectionMethod $handler, string $name): ?object
    {
        foreach ($graph->getMethods($handler->getName()) as $group) {
            foreach ($group as $method) {
                $attribute = $this->reader->firstFunctionMetadata($method, $name);

                if ($attribute !== null) {
                    return $attribute;
                }
            }
        }

        return null;
    }

    /**
     * @param ClassNode $graph
     * @param \ReflectionMethod $handler
     * @return WorkflowPrototype|null
     * @throws \ReflectionException
     */
    private function getPrototype(ClassNode $graph, \ReflectionMethod $handler): ?WorkflowPrototype
    {
        $cronSchedule = $previousRetry = $prototype = $returnType = null;

        foreach ($graph->getMethods($handler->getName()) as $group) {
            //
            $contextualRetry = $previousRetry;

            foreach ($group as $ctx => $method) {
                /** @var MethodRetry $retry */
                $retry = $this->reader->firstFunctionMetadata($method, MethodRetry::class);

                if ($retry !== null) {
                    // Update current retry from previous value
                    if ($previousRetry instanceof MethodRetry) {
                        $retry = $retry->mergeWith($previousRetry);
                    }

                    // Update current context
                    $contextualRetry = $contextualRetry ? $retry->mergeWith($contextualRetry) : $retry;
                }

                // Last CronSchedule
                $cronSchedule = $this->reader->firstFunctionMetadata($method, CronSchedule::class)
                    ?? $cronSchedule
                ;

                // Last ReturnType
                $returnType = $this->reader->firstFunctionMetadata($method, ReturnType::class)
                    ?? $returnType
                ;

                //
                // In the future, workflow methods are available only in
                // those classes that contain the attribute:
                //
                //  - #[WorkflowInterface]
                //
                $interface = $this->reader->firstClassMetadata($ctx->getReflection(), WorkflowInterface::class);

                // In case
                if ($interface === null) {
                    continue;
                }

                if ($prototype === null) {
                    $prototype = $this->findProto($handler, $method);
                }

                if ($prototype !== null && $retry !== null) {
                    $prototype->setMethodRetry($retry);
                }

                if ($prototype !== null && $cronSchedule !== null) {
                    $prototype->setCronSchedule($cronSchedule);
                }

                if ($prototype !== null && $returnType !== null) {
                    $prototype->setReturnType($returnType);
                }
            }

            $previousRetry = $contextualRetry;
        }

        return $prototype;
    }

    /**
     * @param \ReflectionMethod $handler
     * @param \ReflectionMethod $ctx
     * @return WorkflowPrototype|null
     */
    private function findProto(\ReflectionMethod $handler, \ReflectionMethod $ctx): ?WorkflowPrototype
    {
        $reflection = $ctx->getDeclaringClass();

        //
        // The name of the workflow handler must be generated based
        // method's name which can be redefined using #[WorkflowMethod]
        // attribute.
        //
        $info = $this->reader->firstFunctionMetadata($ctx, WorkflowMethod::class);

        if ($info === null) {
            return null;
        }

        /**
         * In the case that one of the handlers is declared on an incorrect
         * method, we should inform about it. For example:
         *
         * <code>
         *  #[WorkflowInterface]
         *  class Workflow extends BaseWorkflow
         *  {
         *      #[WorkflowMethod]
         *      public function handler(): void { ... } // << ALL OK
         *  }
         *
         *  #[WorkflowInterface]
         *  abstract class BaseWorkflow
         *  {
         *      #[WorkflowMethod]
         *      protected function handler(): void { ... } // << Error: Cannot be protected
         *  }
         * </code>
         */
        if (!$this->isValidMethod($handler)) {
            $contextClass = $ctx->getDeclaringClass();

            throw new \LogicException(
                \sprintf(self::ERROR_HANDLER_VISIBILITY, $contextClass->getName(), $ctx->getName())
            );
        }

        $name = $info->name ?? $reflection->getShortName();

        return new WorkflowPrototype($name, $handler, $reflection);
    }
}
