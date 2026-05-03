<?php

/**
 * This file is part of Nexus RPC SDK for PHP package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus\Handler\Internal;

use Temporal\Nexus\Exception\InvalidArgumentException;
use Temporal\Nexus\Handler\OperationHandlerInterface;

/**
 * @internal Static rules for methods carrying `#[OperationImpl]`.
 */
final class OperationImplMethodValidator
{
    /** @codeCoverageIgnore */
    private function __construct() {}

    /**
     * Assert that the method conforms to handler-factory rules (public,
     * non-static, zero parameters, returns {@see OperationHandlerInterface}).
     *
     * @throws InvalidArgumentException on any violation.
     */
    public static function assertSignature(\ReflectionMethod $method): void
    {
        $where = self::whereOf($method);

        if ($method->getNumberOfParameters() > 0) {
            throw new InvalidArgumentException("#[OperationImpl] method {$where} cannot have any parameters");
        }
        if (!$method->isPublic()) {
            throw new InvalidArgumentException("#[OperationImpl] method {$where} must be public");
        }
        if ($method->isStatic()) {
            throw new InvalidArgumentException("#[OperationImpl] method {$where} cannot be static");
        }

        $returnType = $method->getReturnType();
        if (!$returnType instanceof \ReflectionNamedType
            || !self::isOperationHandlerType($returnType->getName())) {
            $declared = $returnType === null ? 'no declared type' : (string) $returnType;
            throw new InvalidArgumentException(\sprintf(
                '#[OperationImpl] method %s must return %s, got %s',
                $where,
                OperationHandlerInterface::class,
                $declared,
            ));
        }
    }

    public static function whereOf(\ReflectionMethod $method): string
    {
        return \sprintf('%s::%s()', $method->getDeclaringClass()->getName(), $method->getName());
    }

    private static function isOperationHandlerType(string $typeName): bool
    {
        return \is_a($typeName, OperationHandlerInterface::class, true);
    }
}
