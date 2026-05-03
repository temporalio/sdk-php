<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler;

use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Handler\Internal\ServiceImplFactory;
use Temporal\Nexus\ServiceDefinition;

/**
 * Live binding of a service implementation to its contract.
 *
 * The implementation is any class that implements a {@see Service}-annotated
 * interface and provides the operation methods declared by that interface.
 * Instances are produced by {@see self::fromInstance()}, which delegates to
 * {@see ServiceImplFactory} for all reflection-driven wiring.
 */
final class ServiceImplInstance
{
    /**
     * @param array<string, OperationHandlerInterface> $operationHandlers
     */
    public function __construct(
        public readonly ServiceDefinition $definition,
        public readonly array $operationHandlers,
    ) {}

    /**
     * Create a service instance from the given implementation object.
     *
     * Failures wrap the root cause as a {@see \Temporal\Nexus\Exception\NexusException}
     * or {@see \Temporal\Nexus\Exception\InvalidArgumentException} — see
     * {@see ServiceImplFactory::build()} for exact semantics.
     */
    public static function fromInstance(object $instance): self
    {
        return ServiceImplFactory::build($instance);
    }
}
