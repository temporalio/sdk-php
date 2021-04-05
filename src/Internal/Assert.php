<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal;

final class Assert
{
    /**
     * @param mixed $value
     * @param class-string $enum
     * @return bool
     */
    public static function enum($value, string $enum): bool
    {
        try {
            $constants = (new \ReflectionClass($enum))->getReflectionConstants();
        } catch (\ReflectionException $_) {
            return false;
        }

        foreach ($constants as $constant) {
            if ($constant->isPublic() && $value === $constant->getValue()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<object> $values
     * @param class-string $of
     * @return bool
     */
    public static function valuesInstanceOf(array $values, string $of): bool
    {
        return self::all($values, fn ($v) => $v instanceof $of);
    }

    /**
     * @param array<class-string> $values
     * @param class-string $of
     * @return bool
     */
    public static function valuesSubclassOfOrSameClass(array $values, string $of): bool
    {
        return self::all($values, fn ($v) => is_a($v, $of, true));
    }

    /**
     * @param array<mixed> $values
     * @param callable $filter
     * @return bool
     */
    public static function all(array $values, callable $filter): bool
    {
        foreach ($values as $key => $value) {
            if (!$filter($value, $key)) {
                return false;
            }
        }

        return true;
    }
}
