<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\Nexus\Validation\OperationNameValidator;

/**
 * Pure storage DTO for a Nexus operation declaration discovered on a
 * `#[Service]`-annotated contract. Built by
 * {@see \Temporal\Internal\Declaration\Reader\NexusServiceReader}.
 *
 * `$handler` is the reflection of the method carrying `#[Operation]` /
 * `#[AsyncOperation]` on the contract. When the contract is an interface
 * separate from the impl, calling `invoke()` on this method against the impl
 * object still resolves correctly via PHP polymorphism.
 *
 * `$cancelHandler` is the impl-side `#[OperationCancel]` method (if any) and is
 * only populated for async operations — sync ops cannot be cancelled and the
 * Reader rejects any cancel routine that targets one.
 */
final class NexusOperationPrototype
{
    /**
     * @param non-empty-string $name Wire-level operation name.
     * @param non-empty-string $methodName PHP method name on the contract.
     * @param string $inputType Either a class-string, scalar, `?T`, `void`, `mixed`.
     * @param string $outputType Same conventions as `$inputType`.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $methodName,
        public readonly string $inputType,
        public readonly string $outputType,
        public readonly bool $async,
        public readonly \ReflectionMethod $handler,
        public readonly ?\ReflectionMethod $cancelHandler = null,
    ) {
        OperationNameValidator::assert($name);
    }

    public function withCancelHandler(\ReflectionMethod $cancelHandler): self
    {
        return new self(
            name: $this->name,
            methodName: $this->methodName,
            inputType: $this->inputType,
            outputType: $this->outputType,
            async: $this->async,
            handler: $this->handler,
            cancelHandler: $cancelHandler,
        );
    }
}
