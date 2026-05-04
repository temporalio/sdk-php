<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Nexus\Validation\ServiceNameValidator;

/**
 * Pure storage DTO for a `#[Service]`-annotated Nexus contract. Mirrors the
 * shape of {@see ActivityPrototype} / {@see WorkflowPrototype}: reflection
 * lives in the Reader, the prototype is a plain getter bag.
 *
 * The base `Prototype::$handler` slot is left null — Nexus services have no
 * single entry-point handler; per-operation handlers are exposed through
 * {@see self::getOperations()}.
 *
 * The `$factory` slot follows {@see ActivityPrototype}: `withInstance(object)`
 * is sugar over `withFactory(static fn() => $instance)`, and
 * {@see \Temporal\Internal\Declaration\Instantiator\NexusServiceInstantiator}
 * uses it at bind time.
 */
final class NexusServicePrototype extends Prototype
{
    /** @var array<string, NexusOperationPrototype> Keyed by wire operation name. */
    private readonly array $operations;

    private ?\Closure $factory = null;

    /**
     * @param non-empty-string $name Wire-level service name.
     * @param array<string, NexusOperationPrototype> $operations
     */
    public function __construct(
        string $name,
        array $operations,
        \ReflectionClass $class,
    ) {
        ServiceNameValidator::assert($name);
        parent::__construct($name, null, $class);
        $this->operations = $operations;
    }

    /**
     * @return array<string, NexusOperationPrototype>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getFactory(): ?\Closure
    {
        return $this->factory;
    }

    /**
     * Bind the given object as the service implementation. Equivalent to
     * `withFactory(static fn() => $instance)` — same shape as
     * {@see ActivityPrototype::withInstance()}.
     */
    public function withInstance(object $instance): self
    {
        return $this->withFactory(static fn(): object => $instance);
    }

    public function withFactory(\Closure $factory): self
    {
        $proto = clone $this;
        $proto->factory = $factory;
        return $proto;
    }
}
