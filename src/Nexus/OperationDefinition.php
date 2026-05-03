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
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Validation\OperationNameValidator;

/**
 * Definition of an operation on a service.
 */
final class OperationDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $inputType,
        public readonly string $outputType,
        public readonly ?string $methodName = null,
    ) {
        OperationNameValidator::assert($name);
    }

    /**
     * Create an OperationDefinition from a reflection method on a {@see \Temporal\Nexus\Attribute\Service}-annotated interface.
     */
    public static function fromMethod(\ReflectionMethod $method): self
    {
        $attributes = $method->getAttributes(Operation::class);
        if (\count($attributes) === 0) {
            throw new InvalidArgumentException('Missing #[Operation] attribute');
        }
        if ($method->getNumberOfParameters() > 1) {
            throw new InvalidArgumentException('Can have no more than one parameter');
        }
        if ($method->isStatic()) {
            throw new InvalidArgumentException('Cannot be static');
        }

        /** @var Operation $operation */
        $operation = $attributes[0]->newInstance();
        $operationName = $operation->name !== '' ? $operation->name : $method->getName();

        $inputType = 'void';
        if ($method->getNumberOfParameters() === 1) {
            $param = $method->getParameters()[0];
            $inputType = self::typeToString(
                $param->getType(),
                "parameter \${$param->getName()} of {$method->getDeclaringClass()->getName()}::{$method->getName()}()",
                untypedFallback: 'mixed',
            );
        }

        $outputType = self::typeToString(
            $method->getReturnType(),
            "return type of {$method->getDeclaringClass()->getName()}::{$method->getName()}()",
            untypedFallback: 'void',
        );

        return new self(
            name: $operationName,
            inputType: $inputType,
            outputType: $outputType,
            methodName: $method->getName(),
        );
    }

    /**
     * Reject union/intersection (one concrete type per slot). Nullable named
     * types keep the `?` prefix.
     *
     * @param non-empty-string $untypedFallback Used when the slot has no type hint.
     * @throws InvalidArgumentException
     */
    private static function typeToString(
        ?\ReflectionType $type,
        string $location,
        string $untypedFallback,
    ): string {
        if ($type === null) {
            return $untypedFallback;
        }

        if ($type instanceof \ReflectionUnionType) {
            throw new InvalidArgumentException(
                "Union types are not supported for {$location}",
            );
        }

        if ($type instanceof \ReflectionIntersectionType) {
            throw new InvalidArgumentException(
                "Intersection types are not supported for {$location}",
            );
        }

        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();
            if ($type->allowsNull() && $name !== 'mixed' && $name !== 'null') {
                return '?' . $name;
            }
            return $name;
        }

        // @codeCoverageIgnoreStart
        // future-proof: unknown ReflectionType subclass
        return $untypedFallback;
        // @codeCoverageIgnoreEnd
    }
}
