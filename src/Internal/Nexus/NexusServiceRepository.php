<?php

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Nexus\Sdk\Handler\ServiceImplInstance;

/**
 * Stores registered Nexus service implementations.
 *
 * Services are identified by their {@see \Nexus\Sdk\ServiceDefinition::$name}.
 * Registering two implementations that declare the same service name is a
 * configuration error and will fail fast at registration time.
 */
final class NexusServiceRepository
{
    /** @var array<string, ServiceImplInstance> */
    private array $byName = [];

    /**
     * Register a Nexus service implementation.
     *
     * @throws \InvalidArgumentException if a service with the same name is already registered.
     */
    public function add(ServiceImplInstance $instance): void
    {
        $name = $instance->definition->name;

        if (isset($this->byName[$name])) {
            $existing = $this->byName[$name];
            throw new \InvalidArgumentException(\sprintf(
                'Nexus service "%s" is already registered. '
                . 'Previous registration: %s; new attempt: %s. '
                . 'Register each service exactly once per worker.',
                $name,
                self::describeInstance($existing),
                self::describeInstance($instance),
            ));
        }

        $this->byName[$name] = $instance;
    }

    /**
     * @return list<ServiceImplInstance>
     */
    public function getInstances(): array
    {
        return \array_values($this->byName);
    }

    private static function describeInstance(ServiceImplInstance $i): string
    {
        $ops = \array_keys($i->definition->operations);
        return \sprintf(
            '%s (ops: %s)',
            $i->definition->name,
            \implode(', ', $ops) ?: '<none>',
        );
    }
}
