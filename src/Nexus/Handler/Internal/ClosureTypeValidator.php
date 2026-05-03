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
use Temporal\Nexus\Handler\ClosureOperationFunction;
use Temporal\Nexus\Handler\OperationHandlerInterface;
use Temporal\Nexus\Handler\SynchronousOperationHandler;
use Temporal\Nexus\OperationDefinition;

/**
 * @internal Best-effort runtime check that a handler's input/output types
 * match the operation. Only inspects {@see SynchronousOperationHandler} over
 * {@see ClosureOperationFunction}; everything else is accepted as-is.
 * Wildcards: `mixed`, `void`, `null`, `never`, ``''``.
 */
final class ClosureTypeValidator
{
    /** @var list<string> */
    private const WILDCARDS = ['mixed', 'void', 'null', 'never', ''];

    /** @codeCoverageIgnore */
    private function __construct() {}

    /**
     * @throws InvalidArgumentException
     */
    public static function validate(
        OperationHandlerInterface $handler,
        OperationDefinition $operation,
        string $where,
    ): void {
        if (!$handler instanceof SynchronousOperationHandler) {
            return;
        }
        $function = $handler->getFunction();
        if (!$function instanceof ClosureOperationFunction) {
            return;
        }

        $reflection = new \ReflectionFunction($function->getClosure());
        $params = $reflection->getParameters();

        $inputParam = $params[2] ?? null;
        if ($inputParam !== null) {
            $inputType = self::typeToComparable($inputParam->getType());
            if (!self::typeMatches($inputType, $operation->inputType)) {
                throw new InvalidArgumentException(\sprintf(
                    '#[OperationImpl] %s handler input type "%s" does not match operation "%s" declared input "%s"',
                    $where,
                    $inputType,
                    $operation->name,
                    $operation->inputType,
                ));
            }
        }

        $returnType = self::typeToComparable($reflection->getReturnType());
        if (!self::typeMatches($returnType, $operation->outputType)) {
            throw new InvalidArgumentException(\sprintf(
                '#[OperationImpl] %s handler return type "%s" does not match operation "%s" declared output "%s"',
                $where,
                $returnType,
                $operation->name,
                $operation->outputType,
            ));
        }
    }

    private static function typeToComparable(?\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }
        return 'mixed';
    }

    private static function typeMatches(string $actual, string $expected): bool
    {
        $a = \ltrim($actual, '?\\');
        $e = \ltrim($expected, '?\\');
        if (\in_array($a, self::WILDCARDS, true) || \in_array($e, self::WILDCARDS, true)) {
            return true;
        }
        return $a === $e;
    }
}
