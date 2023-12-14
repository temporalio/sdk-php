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
     * @param \ReflectionFunctionAbstract $method
     * @param array $args
     * @return list<mixed> Unnamed arguments in the correct order.
     */
    public static function orderArguments(\ReflectionFunctionAbstract $method, array $args): array
    {
        if (\array_is_list($args) || \count($args) === 0) {
            return $args;
        }

        $finalArgs = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (\array_key_exists($name, $args)) {
                $finalArgs[] = $args[$name];
            } else if ($parameter->isDefaultValueAvailable()) {
                $finalArgs[] = $parameter->getDefaultValue();
            } else {
                throw new InvalidArgumentException("Missing argument `$name`.");
            }
        }

        return $finalArgs;
    }
}
