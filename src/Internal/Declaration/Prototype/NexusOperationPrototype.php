<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\Prototype;

use Temporal\DataConverter\Type;
use Temporal\Nexus\Validation\OperationNameValidator;

/**
 * Storage DTO for a Nexus operation declaration built by {@see \Temporal\Internal\Declaration\Reader\NexusServiceReader}.
 */
final class NexusOperationPrototype
{
    /**
     * @param non-empty-string $name Wire-level operation name.
     * @param non-empty-string $methodName PHP method name on the contract.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $methodName,
        public readonly Type $inputType,
        public readonly Type $outputType,
        public readonly bool $async,
        public readonly \ReflectionMethod $handler,
    ) {
        OperationNameValidator::assert($name);
    }
}
