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
        if (count($args) > $method->getNumberOfParameters()) {
            throw new InvalidArgumentException(sprintf(
                'Too many arguments passed to %s, expected %d, got %d.',
                $method->getName(),
                $method->getNumberOfParameters(),
                count($args)
            ));
        }

        if ($args === [] || \array_is_list($args)) {
            return $args;
        }

        $finalArgs = [];

        foreach ($method->getParameters() as $i => $parameter) {
            $name = $parameter->getName();

            if (\array_key_exists($name, $args)) {
                $finalArgs[] = $args[$name];
            } elseif (\array_key_exists($i, $args)) {
                $finalArgs[] = $args[$i];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $finalArgs[] = $parameter->getDefaultValue();
            } else {
                throw new InvalidArgumentException("Missing argument `$name`.");
            }
        }

        return $finalArgs;
    }
}
