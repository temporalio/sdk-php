<?php

declare(strict_types=1);

namespace Temporal\Internal\Support;

use Temporal\Exception\InvalidArgumentException;

/**
 * @internal
 */
final class Reflection
{
    /**
     * Sorts the given arguments according to the order of the method's parameters.
     * If the method has default values, they will be used for missing arguments.
     * If the method has no default values, an exception will be thrown.
     *
     * @template T
     *
     * @param \ReflectionFunctionAbstract $method
     * @param array<int|string, T> $args
     * @return list<T> Unnamed list of arguments in the correct order.
     */
    public static function orderArguments(\ReflectionFunctionAbstract $method, array $args): array
    {
        if ($args === [] || \array_is_list($args)) {
            return $args;
        }

        if (\count($args) > $method->getNumberOfParameters()) {
            throw new InvalidArgumentException(\sprintf(
                'Too many arguments passed to %s: defined %d, got %d.',
                self::methodName($method),
                $method->getNumberOfParameters(),
                \count($args),
            ));
        }

        $finalArgs = [];

        foreach ($method->getParameters() as $i => $parameter) {
            $name = $parameter->getName();

            $isPositional = \array_key_exists($i, $args);
            $isNamed = \array_key_exists($name, $args);

            if ($isPositional && $isNamed) {
                throw new InvalidArgumentException(\sprintf(
                    'Parameter #%d $%s of %s received two conflicting arguments - named and positional.',
                    $i,
                    $name,
                    self::methodName($method),
                ));
            }

            $finalArgs[] = match (true) {
                $isPositional => $args[$i],
                $isNamed => $args[$name],
                $parameter->isDefaultValueAvailable() => $parameter->getDefaultValue(),
                default => throw new InvalidArgumentException("Missing argument `$name`.")
            };
        }

        return $finalArgs;
    }

    private static function methodName(\ReflectionFunctionAbstract $method): string
    {
        if ($method instanceof \ReflectionMethod) {
            // render class and method name
            return $method->getDeclaringClass()->getName() . '::' . $method->getName() . '()';
        }

        return $method->getName() . '()';
    }
}
