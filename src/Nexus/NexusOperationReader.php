<?php

declare(strict_types=1);

namespace Temporal\Nexus;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\Service;

/**
 * Reads #[Service] and #[Operation] attributes from a Nexus service interface.
 */
final class NexusOperationReader
{
    /**
     * Read operation definitions from a #[Service] annotated interface.
     *
     * Walks the interface's own methods first, then any parent interfaces,
     * so `#[Operation]` declarations inherited from a base interface are
     * picked up. The first occurrence of a method name wins — a child
     * interface can override a parent's operation name.
     *
     * @param class-string $class
     * @return array<string, array{name: string, method: string, returnType: string}>
     */
    public static function getOperations(string $class): array
    {
        $reflection = new \ReflectionClass($class);
        $operations = [];

        foreach (self::collectReflections($reflection) as $source) {
            foreach ($source->getMethods() as $method) {
                if (isset($operations[$method->getName()])) {
                    continue;
                }
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
        }

        return $operations;
    }

    /**
     * Get the service name from a #[Service] annotated interface.
     *
     * Throws when the class/interface is missing the attribute entirely —
     * a service without `#[Service]` is almost always a user error, so
     * failing at registration time is preferable to the server-side
     * "service not found".
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
        // `$service->name` is non-empty per its `!== ''` guard; `getShortName()`
        // is non-empty for named classes — anonymous classes return
        // `class@anonymous…` which is still non-empty.
        return $service->name !== '' ? $service->name : $reflection->getShortName();
    }

    /**
     * Yield the target reflection and every interface it (transitively)
     * extends or implements, so attribute lookup walks the full hierarchy.
     *
     * @return iterable<\ReflectionClass>
     */
    private static function collectReflections(\ReflectionClass $reflection): iterable
    {
        yield $reflection;
        foreach ($reflection->getInterfaces() as $parent) {
            yield $parent;
        }
    }
}
