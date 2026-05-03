<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Nexus\Attribute\OperationImpl;
use Temporal\Nexus\Attribute\ServiceImpl;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\ServiceImplInstance;
use Temporal\Nexus\OperationDefinition;
use Temporal\Nexus\ServiceDefinition;

/**
 * @internal Orchestrates reflection-driven construction of {@see ServiceImplInstance}.
 */
final class ServiceImplFactory
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    public static function build(object $instance): ServiceImplInstance
    {
        $reflection = new \ReflectionClass($instance);
        $attributes = $reflection->getAttributes(ServiceImpl::class);

        if (\count($attributes) === 0) {
            throw new InvalidArgumentException(\sprintf(
                'Missing #[ServiceImpl] attribute on %s',
                $reflection->getName(),
            ));
        }

        /** @var ServiceImpl $serviceImpl */
        $serviceImpl = $attributes[0]->newInstance();

        try {
            $serviceDefinition = ServiceDefinition::fromClass($serviceImpl->service);
        } catch (\Exception $e) {
            throw new NexusException(
                "Failed loading #[ServiceImpl] class {$serviceImpl->service}",
                0,
                $e,
            );
        }

        $operationHandlers = self::collectOperationHandlers($reflection, $serviceDefinition, $instance);

        if (\count($operationHandlers) === 0) {
            throw new NexusException(\sprintf(
                'No operation handlers defined on service implementation %s (service %s)',
                $reflection->getName(),
                $serviceDefinition->name,
            ));
        }

        $missingOps = \array_diff_key($serviceDefinition->operations, $operationHandlers);
        if (\count($missingOps) > 0) {
            throw new NexusException(\sprintf(
                'Missing handlers for service operations on %s: %s',
                $reflection->getName(),
                \implode(', ', \array_keys($missingOps)),
            ));
        }

        // @codeCoverageIgnoreStart
        $extraHandlers = \array_diff_key($operationHandlers, $serviceDefinition->operations);
        if (\count($extraHandlers) > 0) {
            throw new NexusException(\sprintf(
                "Operation handlers on %s don't correspond to service operations: %s",
                $reflection->getName(),
                \implode(', ', \array_keys($extraHandlers)),
            ));
        }
        // @codeCoverageIgnoreEnd

        return new ServiceImplInstance($serviceDefinition, $operationHandlers);
    }

    /**
     * @return array<string, OperationHandlerInterface>
     */
    private static function collectOperationHandlers(
        \ReflectionClass $reflection,
        ServiceDefinition $serviceDefinition,
        object $instance,
    ): array {
        $handlers = [];

        foreach ($reflection->getMethods() as $method) {
            if (\count($method->getAttributes(OperationImpl::class)) === 0) {
                continue;
            }

            try {
                self::registerHandler($handlers, $serviceDefinition, $instance, $method);
            } catch (\Exception $e) {
                throw new NexusException(
                    \sprintf(
                        'Failed obtaining operation handler from %s',
                        OperationImplMethodValidator::whereOf($method),
                    ),
                    0,
                    $e,
                );
            }
        }

        return $handlers;
    }

    /**
     * @param array<string, OperationHandlerInterface> $handlers
     */
    private static function registerHandler(
        array &$handlers,
        ServiceDefinition $serviceDefinition,
        object $instance,
        \ReflectionMethod $method,
    ): void {
        OperationImplMethodValidator::assertSignature($method);

        $operationDefinition = self::findOperationByMethodName($serviceDefinition, $method->getName());
        if ($operationDefinition === null) {
            throw new NexusException(\sprintf(
                'No matching #[Operation] declaration for method %s in service interface %s',
                OperationImplMethodValidator::whereOf($method),
                $serviceDefinition->name,
            ));
        }

        /** @var OperationHandlerInterface $handler */
        $handler = $method->invoke($instance);

        // @codeCoverageIgnoreStart
        if (isset($handlers[$operationDefinition->name])) {
            throw new NexusException(\sprintf(
                'Multiple #[OperationImpl] methods bind to operation "%s" on %s',
                $operationDefinition->name,
                $method->getDeclaringClass()->getName(),
            ));
        }
        // @codeCoverageIgnoreEnd

        ClosureTypeValidator::validate(
            $handler,
            $operationDefinition,
            OperationImplMethodValidator::whereOf($method),
        );

        $handlers[$operationDefinition->name] = $handler;
    }

    private static function findOperationByMethodName(
        ServiceDefinition $serviceDefinition,
        string $methodName,
    ): ?OperationDefinition {
        foreach ($serviceDefinition->operations as $opDef) {
            if ($methodName === $opDef->methodName) {
                return $opDef;
            }
        }
        return null;
    }
}
