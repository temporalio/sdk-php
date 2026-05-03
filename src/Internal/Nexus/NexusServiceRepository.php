<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Nexus;

use Temporal\Nexus\Handler\ServiceImplInstance;

/**
 * Stores registered Nexus service implementations keyed by service name.
 * Duplicate registration fails fast.
 */
final class NexusServiceRepository
{
    /** @var array<string, ServiceImplInstance> */
    private array $byName = [];

    /**
     * @throws \InvalidArgumentException on duplicate service name.
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
