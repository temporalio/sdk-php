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
 * Binds a {@see NexusServicePrototype} to its implementation and builds the
 * per-operation handler map keyed by wire operation name.
 */
final class NexusServiceInstantiator
{
    public function instantiate(NexusServicePrototype $prototype): NexusServiceInstance
    {
        $instance = $this->resolveInstance($prototype);
        $handlers = $this->buildOperationHandlers($prototype, $instance);

        return new NexusServiceInstance($prototype, $handlers);
    }

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
        try {
            return $reflection->newInstance();
        } catch (\ReflectionException $e) {
            throw new NexusException(\sprintf(
                'Service implementation for "%s" cannot be instantiated without arguments — bind via withInstance() or withFactory()',
                $prototype->getID(),
            ), 0, $e);
        }
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
