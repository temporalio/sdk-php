<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;

/**
 * Reads #[Service] and #[Operation] attributes from a Nexus service interface.
 */
final class NexusOperationReader
{
    /**
     * Read operation definitions from a #[Service] annotated interface.
     *
     * @param class-string $class
     * @return array<string, array{name: string, method: string, returnType: string}>
     */
    public static function getOperations(string $class): array
    {
        $reflection = new \ReflectionClass($class);
        $operations = [];

        // ReflectionClass::getMethods() on an interface already includes methods
        // inherited from parent interfaces — no need to walk getInterfaces().
        foreach ($reflection->getMethods() as $method) {
            $attrs = $method->getAttributes(Operation::class);
            if ($attrs === []) {
                continue;
            }

            $operation = $attrs[0]->newInstance();
            $name = $operation->name !== '' ? $operation->name : $method->getName();

            $returnType = $method->getReturnType();
            $returnTypeName = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : 'mixed';

            $operations[$method->getName()] = [
                'name' => $name,
                'method' => $method->getName(),
                'returnType' => $returnTypeName,
            ];
        }

        return $operations;
    }

    /**
     * Get the service name from a #[Service] annotated interface.
     *
     * @param class-string $class
     * @return non-empty-string
     */
    public static function getServiceName(string $class): string
    {
        $reflection = new \ReflectionClass($class);
        $attrs = $reflection->getAttributes(Service::class);

        if ($attrs === []) {
            throw new \InvalidArgumentException(\sprintf(
                'Nexus service class %s is missing the #[%s] attribute',
                $class,
                Service::class,
            ));
        }

        $service = $attrs[0]->newInstance();
        return $service->name !== '' ? $service->name : $reflection->getShortName();
    }
}
