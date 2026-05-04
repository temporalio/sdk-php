<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Validation\OperationNameValidator;

final class OperationDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $inputType,
        public readonly string $outputType,
        public readonly bool $async = false,
        public readonly ?string $methodName = null,
    ) {
        OperationNameValidator::assert($name);
    }

    /**
     * Create an OperationDefinition from a reflection method on a {@see \Temporal\Nexus\Attribute\Service}-annotated interface.
     *
     * The method may carry either {@see Operation} (sync) or {@see AsyncOperation} (async).
     * For async operations the method's declared return type must be {@see OperationInfo};
     * the wire output type comes from the {@see AsyncOperation::$output} parameter.
     */
    public static function fromMethod(\ReflectionMethod $method): self
    {
        $syncAttributes = $method->getAttributes(Operation::class);
        $asyncAttributes = $method->getAttributes(AsyncOperation::class);

        if ($syncAttributes === [] && $asyncAttributes === []) {
            throw new InvalidArgumentException('Missing #[Operation] or #[AsyncOperation] attribute');
        }
        if ($syncAttributes !== [] && $asyncAttributes !== []) {
            throw new InvalidArgumentException('Cannot combine #[Operation] and #[AsyncOperation] on the same method');
        }
        if ($method->getNumberOfParameters() > 1) {
            throw new InvalidArgumentException('Can have no more than one parameter');
        }
        if ($method->isStatic()) {
            throw new InvalidArgumentException('Cannot be static');
        }

        $async = $asyncAttributes !== [];

        $inputType = 'void';
        if ($method->getNumberOfParameters() === 1) {
            $parameter = $method->getParameters()[0];
            $inputType = self::typeToString(
                $parameter->getType(),
                "parameter \${$parameter->getName()} of {$method->getDeclaringClass()->getName()}::{$method->getName()}()",
                untypedFallback: 'mixed',
            );
        }

        if ($async) {
            /** @var AsyncOperation $attribute */
            $attribute = $asyncAttributes[0]->newInstance();
            $operationName = $attribute->name !== '' ? $attribute->name : $method->getName();

            $returnType = $method->getReturnType();
            $declaredReturn = self::typeToString(
                $returnType,
                "return type of {$method->getDeclaringClass()->getName()}::{$method->getName()}()",
                untypedFallback: 'void',
            );
            // Accept ?OperationInfo / leading-slash forms — the wire is the same.
            if (\ltrim($declaredReturn, '?\\') !== OperationInfo::class) {
                throw new InvalidArgumentException(\sprintf(
                    '#[AsyncOperation] method %s::%s() must declare return type %s, got %s',
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    OperationInfo::class,
                    $declaredReturn,
                ));
            }

            $outputType = $attribute->output !== '' ? $attribute->output : 'void';
        } else {
            /** @var Operation $attribute */
            $attribute = $syncAttributes[0]->newInstance();
            $operationName = $attribute->name !== '' ? $attribute->name : $method->getName();
            $outputType = self::typeToString(
                $method->getReturnType(),
                "return type of {$method->getDeclaringClass()->getName()}::{$method->getName()}()",
                untypedFallback: 'void',
            );
        }

        return new self(
            name: $operationName,
            inputType: $inputType,
            outputType: $outputType,
            async: $async,
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
