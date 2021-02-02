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
     * @param string $type
     * @return bool
     */
    public static function keyTypeOf(array $values, string $type): bool
    {
        $fn = 'is_' . $type;
        return self::all($values, fn($_, $k) => $fn($k));
    }

    /**
     * @param array<object> $values
     * @param string $type
     * @return bool
     */
    public static function valueTypeOf(array $values, string $type): bool
    {
        $fn = 'is_' . $type;
        return self::all($values, fn($v) => $fn($v));
    }

    /**
     * @param array<object> $values
     * @param class-string $of
     * @return bool
     */
    public static function valuesInstanceOf(array $values, string $of): bool
    {
        return self::all($values, fn($v) => $v instanceof $of);
    }

    /**
     * @param array<object> $values
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
