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
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;

/**
 * @template-extends Reader<NexusServicePrototype>
 *
 * Reflection-driven discovery for Nexus service contracts. Mirrors the role
 * of {@see WorkflowReader} / {@see ActivityReader}: walks the class hierarchy
 * via {@see ClassNode} (cached inheritance graph), resolves the
 * `#[Service]`-annotated contract, parses operation methods, and produces a
 * pure {@see NexusServicePrototype}.
 *
 * When `fromClass` is called against an impl class (rather than the contract
 * interface), the reader also picks up `#[OperationCancel]` methods from the
 * impl and binds them onto the matching operation prototype.
 */
class NexusServiceReader extends Reader
{
    /**
     * @param class-string $class
     */
    public function fromClass(string $class): NexusServicePrototype
    {
        $reflection = new \ReflectionClass($class);
        $graph = new ClassNode($reflection);

        $contract = $this->resolveContract($graph);

        $service = $this->reader->firstClassMetadata($contract, Service::class);
        // resolveContract guarantees a Service attribute is present.
        \assert($service !== null);
        $name = $service->name !== '' ? $service->name : $contract->getShortName();

        $this->assertServiceNamesMatch($graph, $contract, $name);

        $operations = $this->collectOperations($graph);
        $operations = $this->bindCancelHandlers($reflection, $operations);

        return new NexusServicePrototype($name, $operations, $reflection);
    }

    /**
     * Locate the `#[Service]`-bearing type for the given hierarchy in a single
     * pass.
     *
     * If the root reflection carries `#[Service]`, it is the contract — works
     * for both interfaces and classes that put the attribute on themselves.
     * Otherwise, require exactly one `#[Service]`-annotated type across the
     * hierarchy; zero or multiple matches are rejected.
     *
     * @return \ReflectionClass<object>
     */
    private function resolveContract(ClassNode $graph): \ReflectionClass
    {
        $root = $graph->getReflection();
        if ($this->reader->firstClassMetadata($root, Service::class) !== null) {
            return $root;
        }

        $matches = $this->serviceNodes($graph);
        unset($matches[$root->getName()]);

        if ($matches === []) {
            throw new InvalidArgumentException(\sprintf(
                'Missing #[Service] attribute on %s or any implemented interface',
                $root->getName(),
            ));
        }

        if (\count($matches) > 1) {
            throw new InvalidArgumentException(\sprintf(
                '%s implements multiple #[Service] interfaces (%s); ambiguous',
                $root->getName(),
                \implode(', ', \array_keys($matches)),
            ));
        }

        return \reset($matches);
    }

    /**
     * Reject hierarchies whose other `#[Service]` types declare a different
     * service name — one type can't quietly belong to two unrelated services.
     *
     * @param \ReflectionClass<object> $contract
     */
    private function assertServiceNamesMatch(ClassNode $graph, \ReflectionClass $contract, string $name): void
    {
        foreach ($this->serviceNodes($graph) as $node) {
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
     * Collect every `#[Service]`-annotated type across the hierarchy in a
     * single cached pass, keyed by class name (dedup across diamond edges).
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

        foreach ($graph->getAllMethods() as $name => $rootMethod) {
            $first = null;
            /** @var \Traversable<class-string, \ReflectionMethod> $group */
            foreach ($graph->getMethods($name) as $group) {
                foreach ($group as $method) {
                    $attribute = $this->operationAttribute($method);
                    if ($attribute === false) {
                        $operationFailures[] = "{$method->getName()} on {$method->getDeclaringClass()->getName()} "
                            . 'declares both #[Operation] and #[AsyncOperation]';
                        break 2;
                    }
                    if ($attribute === null) {
                        // Plain helpers, cancel routines (#[OperationCancel]),
                        // constructors and other infrastructure are skipped.
                        continue;
                    }

                    try {
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
     * Resolve the operation attribute for a method.
     *
     * - `null`: the method is not an operation (no `#[Operation]` /
     *   `#[AsyncOperation]`) and should be skipped;
     * - `false`: both attributes are present, which is invalid;
     * - otherwise: the single resolved attribute.
     */
    private function operationAttribute(\ReflectionMethod $method): Operation|AsyncOperation|null|false
    {
        $sync = $this->reader->firstFunctionMetadata($method, Operation::class);
        $async = $this->reader->firstFunctionMetadata($method, AsyncOperation::class);

        if ($sync !== null && $async !== null) {
            return false;
        }

        return $sync ?? $async;
    }

    /**
     * Build a {@see NexusOperationPrototype} from a resolved `#[Operation]` /
     * `#[AsyncOperation]` attribute.
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

        $inputType = new Type(Type::TYPE_VOID);
        if ($method->getNumberOfParameters() === 1) {
            $inputType = Type::create($method->getParameters()[0]->getType());
        }

        $async = $attribute instanceof AsyncOperation ? $attribute : null;

        if ($async !== null) {
            $operationName = $async->name !== '' ? $async->name : $method->getName();
            $outputType = Type::create($async->output !== '' ? $async->output : Type::TYPE_VOID);
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
     * Discover `#[OperationCancel]` methods on the impl class and attach them
     * to matching operation prototypes. Validates cancel-method shape (token
     * parameter + void return) and rejects strays.
     *
     * Called against the impl class — when `fromClass` is invoked against a
     * pure interface (caller-side stub), this finds nothing and is a no-op.
     *
     * @param \ReflectionClass<object> $reflection
     * @param array<string, NexusOperationPrototype> $operations
     * @return array<string, NexusOperationPrototype>
     */
    private function bindCancelHandlers(\ReflectionClass $reflection, array $operations): array
    {
        if ($reflection->isInterface()) {
            return $operations;
        }

        $byOperation = [];
        foreach ($reflection->getMethods() as $method) {
            $cancel = $this->reader->firstFunctionMetadata($method, OperationCancel::class);
            if ($cancel === null) {
                continue;
            }

            $this->assertCancelMethodSignature($method);

            $operationName = $cancel->operation;
            if (isset($byOperation[$operationName])) {
                throw new NexusException(\sprintf(
                    'Multiple #[%s(operation: "%s")] methods on %s',
                    OperationCancel::class,
                    $operationName,
                    $reflection->getName(),
                ));
            }
            $byOperation[$operationName] = $method;
        }

        foreach ($byOperation as $operationName => $cancelMethod) {
            if (!isset($operations[$operationName])) {
                throw new NexusException(\sprintf(
                    '#[%s] on %s targets unknown operation(s): %s. Available operations: %s.',
                    OperationCancel::class,
                    $reflection->getName(),
                    $operationName,
                    \implode(', ', \array_keys($operations)),
                ));
            }

            $operationPrototype = $operations[$operationName];
            if (!$operationPrototype->async) {
                // Sync operations have no terminal-state cancellation; binding a
                // cancel routine to one is always a programmer error. Catch it
                // at registration so the user sees it at worker boot rather
                // than on first cancel attempt.
                throw new NexusException(\sprintf(
                    '#[%s(operation: "%s")] on %s::%s() targets a synchronous operation; '
                    . 'only #[%s] operations support cancellation',
                    OperationCancel::class,
                    $operationName,
                    $reflection->getName(),
                    $cancelMethod->getName(),
                    AsyncOperation::class,
                ));
            }

            $operations[$operationName] = $operationPrototype->withCancelHandler($cancelMethod);
        }

        return $operations;
    }

    private function assertCancelMethodSignature(\ReflectionMethod $method): void
    {
        $where = \sprintf('%s::%s()', $method->getDeclaringClass()->getName(), $method->getName());

        if (!$method->isPublic()) {
            throw new InvalidArgumentException("#[OperationCancel] method {$where} must be public");
        }
        if ($method->isStatic()) {
            throw new InvalidArgumentException("#[OperationCancel] method {$where} cannot be static");
        }

        foreach ($method->getParameters() as $parameter) {
            $this->assertCancelParameterType($parameter, $where);
        }

        $returnType = $method->getReturnType();
        if ($returnType instanceof \ReflectionNamedType && $returnType->getName() !== 'void') {
            throw new InvalidArgumentException(\sprintf(
                '#[OperationCancel] method %s must declare return type `void`, got `%s`',
                $where,
                (string) $returnType,
            ));
        }
    }

    /**
     * A cancel-method parameter may be untyped, `mixed`, `string` (operation
     * token) or one of the handler context objects ({@see OperationContext},
     * {@see OperationCancelDetails}). Any other concrete type is rejected so the
     * mistake surfaces at registration rather than as a runtime TypeError.
     */
    private function assertCancelParameterType(\ReflectionParameter $parameter, string $where): void
    {
        $type = $parameter->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return;
        }

        $allowed = ['string', 'mixed', OperationContext::class, OperationCancelDetails::class];
        if (\in_array($type->getName(), $allowed, true)) {
            return;
        }

        throw new InvalidArgumentException(\sprintf(
            '#[OperationCancel] method %s parameter $%s must be typed as `string` (operation token), `%s` or `%s`, got `%s`',
            $where,
            $parameter->getName(),
            OperationContext::class,
            OperationCancelDetails::class,
            (string) $type,
        ));
    }
}
