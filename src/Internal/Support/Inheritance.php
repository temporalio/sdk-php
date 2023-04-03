<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

class Inheritance
{
    /**
     * @param class-string $haystack
     * @param class-string $trait
     * @return bool
     */
    public static function uses(string $haystack, string $trait): bool
    {
        if ($haystack === $trait) {
            return true;
        }

        foreach ((array)\class_uses($haystack) as $used) {
            if ($used === $trait) {
                return true;
            }

            if (self::uses($used, $trait)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string $haystack
     * @param class-string $class
     * @return bool
     */
    public static function extends(string $haystack, string $class): bool
    {
        if ($haystack === $class) {
            return true;
        }

        if (\is_subclass_of($haystack, $class)) {
            return true;
        }

        foreach (\class_parents($haystack) as $parent) {
            if (self::extends($parent, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template A
     *
     * @param class-string $haystack
     * @param class-string<A> $interface
     *
     * @return bool
     * @psalm-assert-if-true class-string<A> $haystack
     */
    public static function implements(string $haystack, string $interface): bool
    {
        if ($haystack === $interface) {
            return true;
        }

        foreach ((array)\class_implements($haystack) as $implements) {
            if ($implements === $interface) {
                return true;
            }

            if (self::implements($implements, $interface)) {
                return true;
            }
        }

        return false;
    }
}
