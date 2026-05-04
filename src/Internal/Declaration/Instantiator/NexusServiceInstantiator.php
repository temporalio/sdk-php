<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Instantiator;

use Temporal\Internal\Declaration\NexusServiceInstance;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Exception\NexusException;
use Temporal\Nexus\Handler\Internal\MethodOperationHandler;

/**
 * Binds a {@see NexusServicePrototype} to a concrete implementation object,
 * producing a {@see NexusServiceInstance} ready for dispatch. Mirrors the role
 * of {@see ActivityInstantiator} / {@see WorkflowInstantiator}; the
 * implementation object is sourced from the prototype's factory closure (set
 * via `NexusServicePrototype::withInstance()` / `::withFactory()`), or
 * constructed via `newInstance()` as a last resort.
 *
 * Does not implement {@see InstantiatorInterface}: the base
 * {@see \Temporal\Internal\Declaration\InstanceInterface} returns a single
 * {@see \Temporal\Internal\Declaration\MethodHandler}, which doesn't fit a
 * Nexus service that dispatches across many operations.
 *
 * All reflection-driven validation lives in
 * {@see \Temporal\Internal\Declaration\Reader\NexusServiceReader}; this class
 * only assembles the per-operation handler map keyed by wire operation name.
 */
final class NexusServiceInstantiator
{
    public function instantiate(NexusServicePrototype $prototype): NexusServiceInstance
    {
        $instance = $this->resolveInstance($prototype);
        $handlers = $this->buildOperationHandlers($prototype, $instance);

        return new NexusServiceInstance($prototype, $instance, $handlers);
    }

    /**
     * @throws \ReflectionException
     */
    private function resolveInstance(NexusServicePrototype $prototype): object
    {
        $factory = $prototype->getFactory();
        if ($factory !== null) {
            $instance = $factory();
            if (!\is_object($instance)) {
                throw new InvalidArgumentException(\sprintf(
                    'Nexus service factory for "%s" must return an object, got %s',
                    $prototype->getID(),
                    \get_debug_type($instance),
                ));
            }
            return $instance;
        }

        $reflection = $prototype->getClass();
        if ($reflection->isInterface() || $reflection->isAbstract()) {
            throw new InvalidArgumentException(\sprintf(
                'Service implementation for "%s" must be an instantiable class — bind via withInstance() or withFactory()',
                $prototype->getID(),
            ));
        }
        return $reflection->newInstance();
    }

    /**
     * @return array<string, MethodOperationHandler>
     */
    private function buildOperationHandlers(NexusServicePrototype $prototype, object $instance): array
    {
        $handlers = [];
        $reflection = new \ReflectionClass($instance);

        foreach ($prototype->getOperations() as $operation) {
            try {
                $startMethod = $reflection->getMethod($operation->methodName);
            } catch (\ReflectionException $e) {
                throw new NexusException(\sprintf(
                    'Service implementation %s is missing method %s() for operation "%s"',
                    $reflection->getName(),
                    $operation->methodName,
                    $operation->name,
                ), 0, $e);
            }

            if (!$startMethod->isPublic() || $startMethod->isStatic() || $startMethod->isAbstract()) {
                throw new NexusException(\sprintf(
                    'Operation method %s::%s() must be public and non-static',
                    $reflection->getName(),
                    $operation->methodName,
                ));
            }

            $handlers[$operation->name] = new MethodOperationHandler(
                instance: $instance,
                startMethod: $startMethod,
                operation: $operation,
            );
        }

        return $handlers;
    }
}
