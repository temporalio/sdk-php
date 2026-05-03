<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\ServiceDefinition;

/**
 * @internal Reflection-driven construction of {@see ServiceImplInstance}.
 *
 * Mirrors Workflow/Activity discovery: the impl class implements a single
 * interface annotated with {@see Service}; that interface IS the contract.
 * Operation methods are matched by name; cancel methods are bound via
 * {@see OperationCancel} attributes.
 */
final class ServiceImplFactory
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct() {}

    public static function build(object $instance): ServiceImplInstance
    {
        $reflection = new \ReflectionClass($instance);

        $contract = self::findContractFromInterfaces($reflection);

        try {
            $serviceDefinition = ServiceDefinition::fromClass($contract->getName());
        } catch (\Exception $e) {
            throw new NexusException(
                "Failed loading Nexus service contract {$contract->getName()} on {$reflection->getName()}",
                0,
                $e,
            );
        }

        $cancelMethods = self::collectCancelMethods($reflection);
        $operationHandlers = self::collectOperationHandlers($reflection, $serviceDefinition, $instance, $cancelMethods);

        return new ServiceImplInstance($serviceDefinition, $operationHandlers);
    }

    /**
     * Walk the implemented interfaces and return the single one carrying {@see Service}.
     * Reject zero or multiple matches — the contract must be unambiguous.
     *
     * @param \ReflectionClass<object> $reflection
     * @return \ReflectionClass<object>
     */
    private static function findContractFromInterfaces(\ReflectionClass $reflection): \ReflectionClass
    {
        $matches = [];
        foreach ($reflection->getInterfaces() as $interface) {
            if ($interface->getAttributes(Service::class) !== []) {
                $matches[$interface->getName()] = $interface;
            }
        }

        if ($matches === []) {
            throw new InvalidArgumentException(\sprintf(
                'Service implementation %s must implement an interface annotated with #[%s]',
                $reflection->getName(),
                Service::class,
            ));
        }

        if (\count($matches) > 1) {
            throw new InvalidArgumentException(\sprintf(
                'Service implementation %s implements multiple #[%s] interfaces (%s); ambiguous',
                $reflection->getName(),
                Service::class,
                \implode(', ', \array_keys($matches)),
            ));
        }

        return \reset($matches);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @return array<string, \ReflectionMethod> map of operation name → cancel method
     */
    private static function collectCancelMethods(\ReflectionClass $reflection): array
    {
        $byOperation = [];
        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(OperationCancel::class);
            if ($attributes === []) {
                continue;
            }

            self::assertCancelMethodSignature($method);

            /** @var OperationCancel $attribute */
            $attribute = $attributes[0]->newInstance();
            $operationName = $attribute->operation;

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

        return $byOperation;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param array<string, \ReflectionMethod> $cancelMethods
     * @return array<string, OperationHandlerInterface>
     */
    private static function collectOperationHandlers(
        \ReflectionClass $reflection,
        ServiceDefinition $serviceDefinition,
        object $instance,
        array $cancelMethods,
    ): array {
        $handlers = [];

        foreach ($serviceDefinition->operations as $operationDefinition) {
            $methodName = $operationDefinition->methodName ?? $operationDefinition->name;
            try {
                $method = $reflection->getMethod($methodName);
            } catch (\ReflectionException $e) {
                throw new NexusException(\sprintf(
                    'Service implementation %s is missing method %s() for operation "%s"',
                    $reflection->getName(),
                    $methodName,
                    $operationDefinition->name,
                ), 0, $e);
            }

            if (!$method->isPublic() || $method->isStatic() || $method->isAbstract()) {
                throw new NexusException(\sprintf(
                    'Operation method %s::%s() must be public and non-static',
                    $reflection->getName(),
                    $methodName,
                ));
            }

            $cancelMethod = $cancelMethods[$operationDefinition->name] ?? null;
            if ($cancelMethod !== null && !$operationDefinition->async) {
                // Sync operations have no terminal-state cancellation; binding a
                // cancel routine to one is always a programmer error. Catch it
                // at registration so the user sees it at worker boot rather
                // than on first cancel attempt.
                throw new NexusException(\sprintf(
                    '#[%s(operation: "%s")] on %s::%s() targets a synchronous operation; '
                    . 'only #[%s] operations support cancellation',
                    OperationCancel::class,
                    $operationDefinition->name,
                    $reflection->getName(),
                    $cancelMethod->getName(),
                    AsyncOperation::class,
                ));
            }

            $handlers[$operationDefinition->name] = new MethodOperationHandler(
                instance: $instance,
                startMethod: $method,
                operation: $operationDefinition,
                cancelMethod: $operationDefinition->async ? $cancelMethod : null,
            );
        }

        // Surface stray cancel attributes for unknown operations. Show available
        // wire-names — `#[OperationCancel(operation:)]` matches the operation's
        // wire name (`#[Operation(name:)]` / `#[AsyncOperation(name:)]`), not the
        // PHP method name, and that distinction is easy to miss.
        $unknownCancels = \array_diff_key($cancelMethods, $handlers);
        if ($unknownCancels !== []) {
            $unknownNames = \array_keys($unknownCancels);
            $availableNames = \array_keys($serviceDefinition->operations);
            throw new NexusException(\sprintf(
                '#[%s] on %s targets unknown operation(s): %s. Available operations: %s.',
                OperationCancel::class,
                $reflection->getName(),
                \implode(', ', $unknownNames),
                \implode(', ', $availableNames),
            ));
        }

        return $handlers;
    }

    private static function assertCancelMethodSignature(\ReflectionMethod $method): void
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
