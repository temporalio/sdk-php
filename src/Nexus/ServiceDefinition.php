<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

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
     * Create a service definition from a #[Service] annotated interface.
     *
     * @param class-string $class
     */
    public static function fromClass(string $class): self
    {
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(Service::class);

        if (\count($attributes) === 0) {
            throw new InvalidArgumentException('Missing #[Service] attribute');
        }
        if (!$reflection->isInterface()) {
            throw new InvalidArgumentException('Must be an interface');
        }

        /** @var Service $service */
        $service = $attributes[0]->newInstance();
        $name = $service->name !== '' ? $service->name : $reflection->getShortName();

        foreach ($reflection->getInterfaces() as $iface) {
            $subAttrs = $iface->getAttributes(Service::class);
            if (\count($subAttrs) === 0) {
                continue;
            }
            /** @var Service $subService */
            $subService = $subAttrs[0]->newInstance();
            $subName = $subService->name !== '' ? $subService->name : $iface->getShortName();
            if ($subName !== $name) {
                throw new InvalidArgumentException(
                    "Interface {$iface->getName()} has a service attribute whose name ({$subName}) "
                    . "does not match the expected name on the final interface ({$name})",
                );
            }
        }

        $allMethods = self::collectMethods($reflection);

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
                        || $firstOperation->outputType !== $thisOperation->outputType) {
                        $operationFailures[] = "{$method->getName()} on {$method->getDeclaringClass()->getName()} "
                            . 'mismatches against another operation of the same name/signature';
                        break;
                    }
                } catch (\Exception $e) {
                    $operationFailures[] = "{$method->getName()} on {$method->getDeclaringClass()->getName()} "
                        . "is invalid: {$e->getMessage()}";
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
        \ReflectionClass $iface,
        array &$groups,
        array &$visited,
    ): void {
        $key = $iface->getName();
        if (isset($visited[$key])) {
            return;
        }
        $visited[$key] = true;

        foreach ($iface->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $iface->getName()) {
                continue;
            }
            $groups[self::methodSignatureKey($method)][] = $method;
        }

        foreach ($iface->getInterfaces() as $parentIface) {
            self::collectMethodsRecursive($parentIface, $groups, $visited);
        }
    }

    private static function methodSignatureKey(\ReflectionMethod $method): string
    {
        $paramTypes = [];
        foreach ($method->getParameters() as $p) {
            $paramTypes[] = self::reflectionTypeKey($p->getType());
        }
        return $method->getName() . '(' . \implode(',', $paramTypes) . ')';
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
