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
 * Storage DTO for a `#[Service]`-annotated Nexus contract; same shape as {@see ActivityPrototype}.
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
