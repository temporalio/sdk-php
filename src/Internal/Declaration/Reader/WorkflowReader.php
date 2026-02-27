<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Temporal\Client\WorkflowOptions;
use Temporal\Common\CronSchedule;
use Temporal\Common\MethodRetry;
use Temporal\Internal\Declaration\Graph\ClassNode;
use Temporal\Internal\Declaration\Prototype\ActivityPrototype;
use Temporal\Internal\Support\OptionsMerger;
use Temporal\Internal\Declaration\Prototype\QueryDefinition;
use Temporal\Internal\Declaration\Prototype\SignalDefinition;
use Temporal\Internal\Declaration\Prototype\UpdateDefinition;
use Temporal\Internal\Declaration\Prototype\WorkflowPrototype;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\UpdateMethod;
use Temporal\Workflow\UpdateValidatorMethod;
use Temporal\Workflow\WorkflowInit;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\WorkflowVersioningBehavior;

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

    private const ERROR_VALIDATOR_WITHOUT_UPDATE_HANDLER =
        'The function %s::%s() is specified as a validator for the Update Handler `%s`, ' .
        'but the Update Handler with that name was not found.'
    ;
    private const ERROR_VALIDATOR_DUPLICATE =
        'The function %s::%s() is specified as a validator for the Update Handler `%s`, ' .
        'but another validator with was already registered for that handler.'
    ;

    /**
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
                return $this->withMethods($graph, $this->getDefaultPrototype($graph));

            case 1:
                return $this->withMethods($graph, \reset($prototypes));

            default:
                $message = \sprintf(self::ERROR_HANDLER_DUPLICATE, $graph, WorkflowMethod::class, \count($prototypes));
                throw new \LogicException($message);
        }
    }

    public function fromObject(object $object): WorkflowPrototype
    {
        return $this->fromClass($object::class);
    }

    /**
     * @return \Traversable<ActivityPrototype>
     * @throws \ReflectionException
     */
    protected function getWorkflowPrototypes(ClassNode $graph): \Traversable
    {
        foreach ($graph->getAllMethods() as $reflection) {
            if (!$this->isValidMethod($reflection)) {
                continue;
            }

            if ($prototype = $this->getPrototype($graph, $reflection)) {
                yield $prototype;
            }
        }
    }

    /**
     * @throws \ReflectionException
     */
    private function withMethods(ClassNode $graph, WorkflowPrototype $prototype): WorkflowPrototype
    {
        $class = $graph->getReflection();

        // Harvest Update Validator methods
        /** @var array<non-empty-string, \ReflectionMethod> $validators */
        $validators = [];
        foreach ($class->getMethods() as $method) {
            /** @var UpdateValidatorMethod|null $validate */
            $validate = $this->getAttributedMethod($graph, $method, UpdateValidatorMethod::class);

            if ($validate === null) {
                continue;
            }

            // Validation
            $this->isValidMethod($method) or throw new \LogicException(\sprintf(
                self::ERROR_COMMON_METHOD_VISIBILITY,
                'validate update',
                $method->getDeclaringClass()->getName(),
                $method->getName(),
            ));

            // Deduplication
            \array_key_exists($validate->forUpdate, $validators) and throw new \LogicException(\sprintf(
                self::ERROR_VALIDATOR_DUPLICATE,
                $method->getDeclaringClass()->getName(),
                $method->getName(),
                $validate->forUpdate,
            ));

            $validators[$validate->forUpdate] = $method;
        }

        foreach ($class->getMethods() as $method) {
            $contextClass = $method->getDeclaringClass();

            // Check WorkflowInit method
            if ($method->isConstructor()) {
                $attr = $this->getAttributedMethod($graph, $method, WorkflowInit::class);

                if ($attr !== null) {
                    $prototype->setHasInitializer(true);
                }

                continue;
            }

            /** @var UpdateMethod|null $update */
            $update = $this->getAttributedMethod($graph, $method, UpdateMethod::class);
            if ($update !== null) {
                // Validation
                $this->isValidMethod($method) or throw new \LogicException(
                    \vsprintf(self::ERROR_COMMON_METHOD_VISIBILITY, [
                        'update',
                        $contextClass->getName(),
                        $method->getName(),
                    ]),
                );

                // Return type
                $attrs = $method->getAttributes(ReturnType::class);
                $returnType = \array_key_exists(0, $attrs)
                    ? $attrs[0]->newInstance()
                    : $method->getReturnType();

                $name = $update->name ?? $method->getName();
                $prototype->addUpdateHandler(
                    new UpdateDefinition(
                        name: $name,
                        description: $update->description,
                        policy: $update->unfinishedPolicy,
                        returnType: $returnType,
                        method: $method,
                        validator: $validators[$name] ?? null,
                    ),
                );
            }

            /** @var SignalMethod|null $signal */
            $signal = $this->getAttributedMethod($graph, $method, SignalMethod::class);

            if ($signal !== null) {
                // Validation
                if (!$this->isValidMethod($method)) {
                    throw new \LogicException(
                        \vsprintf(self::ERROR_COMMON_METHOD_VISIBILITY, [
                            'signal',
                            $contextClass->getName(),
                            $method->getName(),
                        ]),
                    );
                }

                $prototype->addSignalHandler(
                    new SignalDefinition(
                        name: $signal->name ?? $method->getName(),
                        policy: $signal->unfinishedPolicy,
                        method: $method,
                        description: $signal->description,
                    ),
                );
            }

            /** @var QueryMethod|null $query */
            $query = $this->getAttributedMethod($graph, $method, QueryMethod::class);

            if ($query !== null) {
                // Validation
                if (!$this->isValidMethod($method)) {
                    throw new \LogicException(
                        \vsprintf(self::ERROR_COMMON_METHOD_VISIBILITY, [
                            'query',
                            $contextClass->getName(),
                            $method->getName(),
                        ]),
                    );
                }

                $prototype->addQueryHandler(
                    new QueryDefinition(
                        name: $query->name ?? $method->getName(),
                        returnType: $method->getReturnType(),
                        method: $method,
                        description: $query->description,
                    ),
                );
            }
        }

        return $prototype;
    }

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
            \sprintf(self::ERROR_WORKFLOW_INTERFACE_NOT_FOUND, $graph, WorkflowInterface::class),
        );
    }

    /**
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
     * Walk through the method hierarchy and build the prototype for the workflow method.
     *
     * @throws \ReflectionException
     */
    private function getPrototype(ClassNode $graph, \ReflectionMethod $handler): ?WorkflowPrototype
    {
        $cronSchedule = $previousRetry = $prototype = $returnType = $versionBehavior = $previousOptions = null;

        foreach ($graph->getMethods($handler->getName(), true) as $group) {
            $contextualRetry = $previousRetry;
            $contextualOptions = $previousOptions;

            /** @var \Traversable<ClassNode, \ReflectionMethod> $group */
            foreach ($group as $classNode => $method) {
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

                // Version Behavior
                $versionBehavior = $this->reader->firstFunctionMetadata($method, WorkflowVersioningBehavior::class)
                    ?? $versionBehavior
                ;

                // Collect granular options: previous → class attributes → method attributes
                $options = OptionsMerger::mergeHierarchy(
                    WorkflowOptions::fromReflection($classNode->getReflection()),
                    WorkflowOptions::fromReflection($method),
                    $previousOptions,
                );

                $contextualOptions = $contextualOptions
                    ? OptionsMerger::merge($options, $contextualOptions)
                    : $options;

                //
                // In the future, workflow methods are available only in
                // those classes that contain the attribute:
                //
                //  - #[WorkflowInterface]
                //
                /** @var \ReflectionClass|null $context */
                $interface = $context = null;
                foreach ($graph->getIterator() as $edges) {
                    foreach ($edges as $node) {
                        $interface = $this->reader->firstClassMetadata(
                            $context = $node->getReflection(),
                            WorkflowInterface::class,
                        );

                        if ($interface !== null) {
                            break 2;
                        }
                    }
                }

                // Skip if no interface found
                if ($interface === null) {
                    continue;
                }

                \assert($context !== null);
                $prototype ??= $this->findProto($handler, $method, $context, $graph->getReflection());

                $prototype?->setMethodOptions($options);
                $retry === null or $prototype?->setMethodRetry($retry);
                $cronSchedule === null or $prototype?->setCronSchedule($cronSchedule);
                $returnType === null or $prototype?->setReturnType($returnType);
                $versionBehavior === null or $prototype?->setVersioningBehavior($versionBehavior->value);
            }

            $previousRetry = $contextualRetry;
            $previousOptions = $contextualOptions;
        }

        return $prototype;
    }

    private function getDefaultPrototype(ClassNode $graph): WorkflowPrototype
    {
        return new WorkflowPrototype($graph->getReflection()->getName(), null, $graph->getReflection());
    }

    /**
     * @param \ReflectionMethod $handler First method in the inheritance chain
     * @param \ReflectionMethod $ctx Current method in the inheritance chain
     * @param \ReflectionClass $interface Class or Interface with #[WorkflowInterface] attribute
     * @param \ReflectionClass $class Target class
     */
    private function findProto(
        \ReflectionMethod $handler,
        \ReflectionMethod $ctx,
        \ReflectionClass $interface,
        \ReflectionClass $class,
    ): ?WorkflowPrototype {
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
         * ```php
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
         * ```
         */
        if (!$this->isValidMethod($handler)) {
            $contextClass = $ctx->getDeclaringClass();

            throw new \LogicException(
                \sprintf(self::ERROR_HANDLER_VISIBILITY, $contextClass->getName(), $ctx->getName()),
            );
        }

        $name = $info->name ?? $interface->getShortName();

        return new WorkflowPrototype($name, $handler, $class);
    }
}
