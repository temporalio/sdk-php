<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Reader;

use Temporal\Internal\Declaration\Prototype\NexusOperationPrototype;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\OperationInfo;

/**
 * @template-extends Reader<NexusServicePrototype>
 *
 * Reflection-driven discovery for Nexus service contracts. Mirrors the role
 * of {@see WorkflowReader} / {@see ActivityReader}: walks the class hierarchy
 * via `ReaderInterface` (Spiral attributes), resolves the
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
        $contract = $this->resolveContract($reflection);

        $service = $this->reader->firstClassMetadata($contract, Service::class);
        // resolveContract guarantees a Service attribute is present.
        \assert($service !== null);
        $name = $service->name !== '' ? $service->name : $contract->getShortName();

        $this->assertParentInterfacesMatch($reflection, $contract, $name);

        $operations = $this->collectOperations($contract);
        $operations = $this->bindCancelHandlers($reflection, $operations);

        return new NexusServicePrototype($name, $operations, $reflection);
    }

    /**
     * Locate the `#[Service]`-bearing type for the given reflection.
     *
     * If the reflection itself carries `#[Service]`, it is the contract — works for both
     * interfaces and classes that put the attribute on themselves. Otherwise, walk the
     * implemented interfaces and require exactly one `#[Service]`-annotated interface;
     * zero or multiple matches are rejected.
     *
     * @param \ReflectionClass<object> $reflection
     * @return \ReflectionClass<object>
     */
    private function resolveContract(\ReflectionClass $reflection): \ReflectionClass
    {
        if ($this->reader->firstClassMetadata($reflection, Service::class) !== null) {
            return $reflection;
        }

        $matches = [];
        foreach ($reflection->getInterfaces() as $parentInterface) {
            if ($this->reader->firstClassMetadata($parentInterface, Service::class) !== null) {
                $matches[$parentInterface->getName()] = $parentInterface;
            }
        }

        if ($matches === []) {
            throw new InvalidArgumentException(\sprintf(
                'Missing #[Service] attribute on %s or any implemented interface',
                $reflection->getName(),
            ));
        }

        if (\count($matches) > 1) {
            throw new InvalidArgumentException(\sprintf(
                '%s implements multiple #[Service] interfaces (%s); ambiguous',
                $reflection->getName(),
                \implode(', ', \array_keys($matches)),
            ));
        }

        return \reset($matches);
    }

    /**
     * Reject impls whose super-interfaces declare a different service name —
     * one impl class can't quietly belong to two unrelated services.
     *
     * @param \ReflectionClass<object> $reflection
     * @param \ReflectionClass<object> $contract
     */
    private function assertParentInterfacesMatch(
        \ReflectionClass $reflection,
        \ReflectionClass $contract,
        string $name,
    ): void {
        foreach ($reflection->getInterfaces() as $parentInterface) {
            if ($parentInterface->getName() === $contract->getName()) {
                continue;
            }
            $parentService = $this->reader->firstClassMetadata($parentInterface, Service::class);
            if ($parentService === null) {
                continue;
            }
            $parentName = $parentService->name !== '' ? $parentService->name : $parentInterface->getShortName();
            if ($parentName !== $name) {
                throw new InvalidArgumentException(
                    "Interface {$parentInterface->getName()} has a service attribute whose name ({$parentName}) "
                    . "does not match the expected name on the contract ({$name})",
                );
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $contract
     * @return array<string, NexusOperationPrototype>
     */
    private function collectOperations(\ReflectionClass $contract): array
    {
        $allMethods = $this->collectMethods($contract);

        $operationFailures = [];
        $operations = [];

        foreach ($allMethods as $methodGroup) {
            $first = null;
            foreach ($methodGroup as $method) {
                try {
                    $current = $this->operationFromMethod($method);
                    if ($first === null) {
                        $first = $current;
                        if (isset($operations[$first->name])) {
                            $operationFailures[] = "Multiple operations named '{$first->name}'";
                            break;
                        }
                        $operations[$first->name] = $first;
                    } elseif (
                        $first->name !== $current->name
                        || $first->inputType !== $current->inputType
                        || $first->outputType !== $current->outputType
                    ) {
                        $operationFailures[] = "{$method->getName()} on {$method->getDeclaringClass()->getName()} "
                            . 'mismatches against another operation of the same name/signature';
                        break;
                    }
                } catch (\Exception $exception) {
                    $operationFailures[] = "{$method->getName()} on {$method->getDeclaringClass()->getName()} "
                        . "is invalid: {$exception->getMessage()}";
                    break;
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
     * Group methods by signature (name + parameter types) so override chains
     * land in the same bucket — first wins, mismatches in later overrides
     * are flagged.
     *
     * @param \ReflectionClass<object> $reflection
     * @return list<\ReflectionMethod[]>
     */
    private function collectMethods(\ReflectionClass $reflection): array
    {
        $groups = [];
        $visited = [];
        $this->collectMethodsRecursive($reflection, $groups, $visited);
        return \array_values($groups);
    }

    /**
     * @param \ReflectionClass<object> $contract
     * @param array<string, \ReflectionMethod[]> $groups Keyed by structural signature.
     * @param array<string, true> $visited
     */
    private function collectMethodsRecursive(
        \ReflectionClass $contract,
        array &$groups,
        array &$visited,
    ): void {
        $key = $contract->getName();
        if (isset($visited[$key])) {
            return;
        }
        $visited[$key] = true;

        foreach ($contract->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $contract->getName()) {
                continue;
            }
            // Only methods carrying #[Operation] or #[AsyncOperation] are operations.
            // Plain helpers, cancel routines (#[OperationCancel]), constructors and
            // other infrastructure on a #[Service]-annotated class are skipped here.
            if (
                $this->reader->firstFunctionMetadata($method, Operation::class) === null
                && $this->reader->firstFunctionMetadata($method, AsyncOperation::class) === null
            ) {
                continue;
            }
            $groups[$this->methodSignatureKey($method)][] = $method;
        }

        foreach ($contract->getInterfaces() as $parentInterface) {
            $this->collectMethodsRecursive($parentInterface, $groups, $visited);
        }
    }

    private function methodSignatureKey(\ReflectionMethod $method): string
    {
        $parameterTypes = [];
        foreach ($method->getParameters() as $parameter) {
            $parameterTypes[] = $this->reflectionTypeKey($parameter->getType());
        }
        return $method->getName() . '(' . \implode(',', $parameterTypes) . ')';
    }

    private function reflectionTypeKey(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }
        if ($type instanceof \ReflectionNamedType) {
            return ($type->allowsNull() ? '?' : '') . $type->getName();
        }
        return (string) $type;
    }

    /**
     * Build a {@see NexusOperationPrototype} from a `#[Operation]` /
     * `#[AsyncOperation]`-annotated method.
     */
    private function operationFromMethod(\ReflectionMethod $method): NexusOperationPrototype
    {
        $sync = $this->reader->firstFunctionMetadata($method, Operation::class);
        $async = $this->reader->firstFunctionMetadata($method, AsyncOperation::class);

        if ($sync === null && $async === null) {
            throw new InvalidArgumentException('Missing #[Operation] or #[AsyncOperation] attribute');
        }
        if ($sync !== null && $async !== null) {
            throw new InvalidArgumentException('Cannot combine #[Operation] and #[AsyncOperation] on the same method');
        }
        if ($method->getNumberOfParameters() > 1) {
            throw new InvalidArgumentException('Can have no more than one parameter');
        }
        if ($method->isStatic()) {
            throw new InvalidArgumentException('Cannot be static');
        }

        $inputType = 'void';
        if ($method->getNumberOfParameters() === 1) {
            $parameter = $method->getParameters()[0];
            $inputType = $this->typeToString(
                $parameter->getType(),
                "parameter \${$parameter->getName()} of {$method->getDeclaringClass()->getName()}::{$method->getName()}()",
                untypedFallback: 'mixed',
            );
        }

        if ($async !== null) {
            $operationName = $async->name !== '' ? $async->name : $method->getName();

            $declaredReturn = $this->typeToString(
                $method->getReturnType(),
                "return type of {$method->getDeclaringClass()->getName()}::{$method->getName()}()",
                untypedFallback: 'void',
            );
            // Accept ?OperationInfo / leading-slash forms — the wire is the same.
            if (\ltrim($declaredReturn, '?\\') !== OperationInfo::class) {
                throw new InvalidArgumentException(\sprintf(
                    '#[AsyncOperation] method %s::%s() must declare return type %s, got %s',
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    OperationInfo::class,
                    $declaredReturn,
                ));
            }

            $outputType = $async->output !== '' ? $async->output : 'void';
        } else {
            $operationName = $sync->name !== '' ? $sync->name : $method->getName();
            $outputType = $this->typeToString(
                $method->getReturnType(),
                "return type of {$method->getDeclaringClass()->getName()}::{$method->getName()}()",
                untypedFallback: 'void',
            );
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
     * Reject union/intersection (one concrete type per slot). Nullable named
     * types keep the `?` prefix.
     *
     * @param non-empty-string $untypedFallback Used when the slot has no type hint.
     */
    private function typeToString(
        ?\ReflectionType $type,
        string $location,
        string $untypedFallback,
    ): string {
        if ($type === null) {
            return $untypedFallback;
        }

        if ($type instanceof \ReflectionUnionType) {
            throw new InvalidArgumentException(
                "Union types are not supported for {$location}",
            );
        }

        if ($type instanceof \ReflectionIntersectionType) {
            throw new InvalidArgumentException(
                "Intersection types are not supported for {$location}",
            );
        }

        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();
            if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
                return '?' . $name;
            }
            return $name;
        }

        // @codeCoverageIgnoreStart
        // future-proof: unknown ReflectionType subclass
        return $untypedFallback;
        // @codeCoverageIgnoreEnd
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
        if ($method->getNumberOfParameters() < 1) {
            throw new InvalidArgumentException(
                "#[OperationCancel] method {$where} must accept the operation token as its first parameter",
            );
        }

        // Operation tokens are always strings on the wire. Allow `string`,
        // untyped, or `mixed`; reject any other concrete type so the mistake
        // surfaces at registration rather than as a runtime TypeError.
        $tokenParameter = $method->getParameters()[0];
        $tokenType = $tokenParameter->getType();
        if (
            $tokenType instanceof \ReflectionNamedType
            && $tokenType->getName() !== 'string'
            && $tokenType->getName() !== 'mixed'
        ) {
            throw new InvalidArgumentException(\sprintf(
                '#[OperationCancel] method %s must declare its first parameter as `string` (operation token), got `%s`',
                $where,
                (string) $tokenType,
            ));
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
}
