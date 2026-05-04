<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Validation\ServiceNameValidator;

/**
 * Definition of a service with operations.
 */
final class ServiceDefinition
{
    /**
     * @param array<string, OperationDefinition> $operations
     */
    public function __construct(
        public readonly string $name,
        public readonly array $operations,
    ) {
        ServiceNameValidator::assert($name);
    }

    /**
     * Create a service definition from a `#[Service]`-annotated type.
     *
     * Accepts either:
     *   - an interface carrying `#[Service]` (the interface IS the contract); or
     *   - a class carrying `#[Service]` directly (the class is its own contract); or
     *   - a class implementing exactly one interface that carries `#[Service]` (that
     *     interface is the contract).
     *
     * @param class-string $class
     */
    public static function fromClass(string $class): self
    {
        $reflection = new \ReflectionClass($class);
        $contract = self::resolveContract($reflection);

        /** @var Service $service */
        $service = $contract->getAttributes(Service::class)[0]->newInstance();
        $name = $service->name !== '' ? $service->name : $contract->getShortName();

        foreach ($reflection->getInterfaces() as $parentInterface) {
            if ($parentInterface->getName() === $contract->getName()) {
                continue;
            }
            $subAttributes = $parentInterface->getAttributes(Service::class);
            if (\count($subAttributes) === 0) {
                continue;
            }
            /** @var Service $subService */
            $subService = $subAttributes[0]->newInstance();
            $subName = $subService->name !== '' ? $subService->name : $parentInterface->getShortName();
            if ($subName !== $name) {
                throw new InvalidArgumentException(
                    "Interface {$parentInterface->getName()} has a service attribute whose name ({$subName}) "
                    . "does not match the expected name on the contract ({$name})",
                );
            }
        }

        $allMethods = self::collectMethods($contract);

        $operationFailures = [];
        $operations = [];

        foreach ($allMethods as $methodGroup) {
            $firstOperation = null;
            foreach ($methodGroup as $method) {
                try {
                    $thisOperation = OperationDefinition::fromMethod($method);
                    if ($firstOperation === null) {
                        $firstOperation = $thisOperation;
                        if (isset($operations[$firstOperation->name])) {
                            $operationFailures[] = "Multiple operations named '{$firstOperation->name}'";
                            break;
                        }
                        $operations[$firstOperation->name] = $firstOperation;
                    } elseif ($firstOperation->name !== $thisOperation->name
                        || $firstOperation->inputType !== $thisOperation->inputType
                        || $firstOperation->outputType !== $thisOperation->outputType
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

        return new self($name, $operations);
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
    private static function resolveContract(\ReflectionClass $reflection): \ReflectionClass
    {
        if ($reflection->getAttributes(Service::class) !== []) {
            return $reflection;
        }

        $matches = [];
        foreach ($reflection->getInterfaces() as $parentInterface) {
            if ($parentInterface->getAttributes(Service::class) !== []) {
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
     * Group methods by signature (name + parameter types) for override detection.
     *
     * @return list<\ReflectionMethod[]>
     */
    private static function collectMethods(\ReflectionClass $reflection): array
    {
        $groups = [];
        $visited = [];
        self::collectMethodsRecursive($reflection, $groups, $visited);
        return \array_values($groups);
    }

    /**
     * @param array<string, \ReflectionMethod[]> $groups Keyed by structural signature.
     * @param array<string, true> $visited
     */
    private static function collectMethodsRecursive(
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
                $method->getAttributes(Operation::class) === []
                && $method->getAttributes(AsyncOperation::class) === []
            ) {
                continue;
            }
            $groups[self::methodSignatureKey($method)][] = $method;
        }

        foreach ($contract->getInterfaces() as $parentInterface) {
            self::collectMethodsRecursive($parentInterface, $groups, $visited);
        }
    }

    private static function methodSignatureKey(\ReflectionMethod $method): string
    {
        $parameterTypes = [];
        foreach ($method->getParameters() as $parameter) {
            $parameterTypes[] = self::reflectionTypeKey($parameter->getType());
        }
        return $method->getName() . '(' . \implode(',', $parameterTypes) . ')';
    }

    private static function reflectionTypeKey(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }
        if ($type instanceof \ReflectionNamedType) {
            return ($type->allowsNull() ? '?' : '') . $type->getName();
        }
        return (string) $type;
    }
}
