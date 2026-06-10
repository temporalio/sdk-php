<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Temporal\DataConverter\Type;
use Temporal\Internal\Declaration\Graph\ClassNode;
use Temporal\Internal\Declaration\Prototype\NexusOperationPrototype;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\WorkflowHandle;

/**
 * Reflection-driven discovery of `#[Service]` contracts producing a {@see NexusServicePrototype}.
 *
 * @template-extends Reader<NexusServicePrototype>
 */
class NexusServiceReader extends Reader
{
    /**
     * @internal
     */
    public static function returnsOperationHandler(\ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();

        return $returnType instanceof \ReflectionNamedType
            && !$returnType->allowsNull()
            && !$returnType->isBuiltin()
            && \is_a($returnType->getName(), OperationHandlerInterface::class, true);
    }

    /**
     * @param class-string $class
     */
    public function fromClass(string $class): NexusServicePrototype
    {
        $reflection = new \ReflectionClass($class);
        $graph = new ClassNode($reflection);

        $serviceNodes = $this->serviceNodes($graph);
        $contract = $this->resolveContract($reflection, $serviceNodes);

        $service = $this->reader->firstClassMetadata($contract, Service::class);
        // resolveContract guarantees a Service attribute is present.
        \assert($service !== null);
        $name = $service->name !== '' ? $service->name : $contract->getShortName();

        $this->assertServiceNamesMatch($serviceNodes, $contract, $name);

        $operations = $this->collectOperations($graph);

        return new NexusServicePrototype($name, $operations, $reflection);
    }

    /**
     * Locate the single `#[Service]`-bearing type for the hierarchy; the root wins if annotated.
     *
     * @param \ReflectionClass<object> $root
     * @param array<class-string, \ReflectionClass<object>> $matches
     * @return \ReflectionClass<object>
     */
    private function resolveContract(\ReflectionClass $root, array $matches): \ReflectionClass
    {
        if ($this->reader->firstClassMetadata($root, Service::class) !== null) {
            return $root;
        }

        if ($matches === []) {
            throw new InvalidArgumentException(\sprintf(
                'Missing #[Service] attribute on %s or any implemented interface',
                $root->getName(),
            ));
        }

        if (\count($matches) > 1) {
            throw new InvalidArgumentException(\sprintf(
                '%s implements multiple #[Service] types (%s); ambiguous',
                $root->getName(),
                \implode(', ', \array_keys($matches)),
            ));
        }

        return \reset($matches);
    }

    /**
     * Reject hierarchies whose other `#[Service]` types declare a different service name.
     *
     * @param array<class-string, \ReflectionClass<object>> $serviceNodes
     * @param \ReflectionClass<object> $contract
     */
    private function assertServiceNamesMatch(array $serviceNodes, \ReflectionClass $contract, string $name): void
    {
        foreach ($serviceNodes as $node) {
            if ($node->getName() === $contract->getName()) {
                continue;
            }

            $service = $this->reader->firstClassMetadata($node, Service::class);
            \assert($service !== null);
            $nodeName = $service->name !== '' ? $service->name : $node->getShortName();
            if ($nodeName !== $name) {
                throw new InvalidArgumentException(
                    "Interface {$node->getName()} has a service attribute whose name ({$nodeName}) "
                    . "does not match the expected name on the contract ({$name})",
                );
            }
        }
    }

    /**
     * Collect every `#[Service]`-annotated type across the hierarchy, keyed by class name.
     *
     * @return array<class-string, \ReflectionClass<object>>
     */
    private function serviceNodes(ClassNode $graph): array
    {
        $matches = [];
        foreach ($graph as $edge) {
            foreach ($edge as $node) {
                $reflection = $node->getReflection();
                if ($this->reader->firstClassMetadata($reflection, Service::class) !== null) {
                    $matches[$reflection->getName()] = $reflection;
                }
            }
        }

        return $matches;
    }

    /**
     * @return array<string, NexusOperationPrototype>
     */
    private function collectOperations(ClassNode $graph): array
    {
        $operationFailures = [];
        $operations = [];

        foreach (\array_keys($graph->getAllMethods()) as $name) {
            $first = null;
            /** @var \Traversable<ClassNode, \ReflectionMethod> $group */
            foreach ($graph->getMethods($name) as $group) {
                foreach ($group as $method) {
                    try {
                        $attribute = $this->operationAttribute($method);
                        if ($attribute === null) {
                            continue;
                        }

                        $current = $this->operationFromMethod($method, $attribute);
                        if ($first === null) {
                            $first = $current;
                            if (isset($operations[$first->name])) {
                                $operationFailures[] = "Multiple operations named '{$first->name}'";
                                break 2;
                            }
                            $operations[$first->name] = $first;
                        } elseif (
                            $first->name !== $current->name
                            || $first->inputType != $current->inputType
                            || $first->outputType != $current->outputType
                        ) {
                            $operationFailures[] = "{$method->getName()} on {$method->getDeclaringClass()->getName()} "
                                . 'mismatches against another operation of the same name/signature';
                            break 2;
                        }
                    } catch (\Exception $exception) {
                        $operationFailures[] = "{$method->getName()} on {$method->getDeclaringClass()->getName()} "
                            . "is invalid: {$exception->getMessage()}";
                        break 2;
                    }
                }
            }
        }

        if (\count($operationFailures) > 0) {
            throw new InvalidArgumentException(
                \count($operationFailures) . ' operation(s) were invalid, reasons: '
                . \implode(', ', $operationFailures),
            );
        }

        if (\count($operations) === 0) {
            throw new InvalidArgumentException('No operations defined');
        }

        return $operations;
    }

    /**
     * Resolve the operation attribute for a method; `null` means the method is not an operation.
     */
    private function operationAttribute(\ReflectionMethod $method): Operation|AsyncOperation|null
    {
        $sync = $this->reader->firstFunctionMetadata($method, Operation::class);
        $async = $this->reader->firstFunctionMetadata($method, AsyncOperation::class);

        if ($sync !== null && $async !== null) {
            throw new InvalidArgumentException('declares both #[Operation] and #[AsyncOperation]');
        }

        return $sync ?? $async;
    }

    /**
     * Build a {@see NexusOperationPrototype} from a resolved `#[Operation]` / `#[AsyncOperation]` attribute.
     */
    private function operationFromMethod(
        \ReflectionMethod $method,
        Operation|AsyncOperation $attribute,
    ): NexusOperationPrototype {
        if ($method->getNumberOfParameters() > 1) {
            throw new InvalidArgumentException('Can have no more than one parameter');
        }
        if ($method->isStatic()) {
            throw new InvalidArgumentException('Cannot be static');
        }
        if (!$this->isValidMethod($method)) {
            throw new InvalidArgumentException('Must be public');
        }

        $inputType = new Type(Type::TYPE_VOID);
        if ($method->getNumberOfParameters() === 1) {
            $inputType = Type::create($method->getParameters()[0]->getType());
        }

        $async = $attribute instanceof AsyncOperation ? $attribute : null;

        if ($async !== null) {
            $this->assertAsyncReturnType($method);
            $operationName = $async->name !== '' ? $async->name : $method->getName();
            $outputType = Type::create($async->output !== '' ? $async->output : Type::TYPE_VOID);

            if (self::returnsOperationHandler($method)) {
                if ($method->getNumberOfParameters() !== 0) {
                    throw new InvalidArgumentException(
                        'Operation handler factory must declare no parameters; '
                        . 'the input arrives in OperationHandlerInterface::start()',
                    );
                }
                $inputType = Type::create($async->input !== '' ? $async->input : Type::TYPE_ANY);
            }
        } else {
            $operationName = $attribute->name !== '' ? $attribute->name : $method->getName();
            $outputType = Type::create($method->getReturnType());
        }

        return new NexusOperationPrototype(
            name: $operationName,
            methodName: $method->getName(),
            inputType: $inputType,
            outputType: $outputType,
            async: $async !== null,
            handler: $method,
        );
    }

    /**
     * An `#[AsyncOperation]` method must return a non-nullable `WorkflowHandle`
     * (SDK-managed workflow run) or an `OperationHandlerInterface` implementation
     * (manual operation owning both start and cancel).
     */
    private function assertAsyncReturnType(\ReflectionMethod $method): void
    {
        $returnType = $method->getReturnType();
        if (
            $returnType instanceof \ReflectionNamedType
            && !$returnType->allowsNull()
            && $returnType->getName() === WorkflowHandle::class
        ) {
            return;
        }
        if (self::returnsOperationHandler($method)) {
            return;
        }

        throw new InvalidArgumentException(\sprintf(
            '#[%s] method %s::%s() must declare a `%s` return type or return an `%s` implementation',
            AsyncOperation::class,
            $method->getDeclaringClass()->getName(),
            $method->getName(),
            WorkflowHandle::class,
            OperationHandlerInterface::class,
        ));
    }
}
