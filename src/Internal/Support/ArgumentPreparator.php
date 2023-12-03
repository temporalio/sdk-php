<?php

namespace Temporal\Internal\Support;

class ArgumentPreparator
{
    public static function alignArgs(array $args, \ReflectionFunctionAbstract $method): array
    {
        if (array_is_list($args) || count($args) === 0) {
            return $args;
        }

        $finalArgs = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $args)) {
                $finalArgs[$name] = $args[$name];
            } else if ($parameter->isDefaultValueAvailable()) {
                $finalArgs[$name] = $parameter->getDefaultValue();
            } else {
                throw new \InvalidArgumentException("Missing argument: $name");
            }
        }

        return $finalArgs;
    }
}