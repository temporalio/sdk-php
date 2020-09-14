<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Common;

use Fun\Symbol\Symbol;

class Arrayable
{
    /**
     * @var string
     */
    protected const DEPTH_DELIMITER = '.';

    /**
     * @param mixed $arrayable
     * @return bool
     */
    public static function accessible($arrayable): bool
    {
        return \is_array($arrayable) || $arrayable instanceof \ArrayAccess;
    }

    /**
     * @param array|\ArrayAccess $array
     * @param string $key
     * @return bool
     */
    public static function exists($array, string $key): bool
    {
        assert(self::accessible($array));

        if ($array instanceof \ArrayAccess) {
            return $array->offsetExists($key);
        }

        return \array_key_exists($key, $array);
    }

    /**
     * @param array|\ArrayAccess $array
     * @param string ...$keys
     * @return bool
     */
    public static function has($array, string ...$keys): bool
    {
        $symbol = Symbol::create();

        foreach ($keys as $key) {
            if (static::get($array, $key, $symbol) === $symbol) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array|\ArrayAccess $array
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public static function get($array, string $key, $default = null)
    {
        if (! static::accessible($array)) {
            return $default;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (\strpos($key, static::DEPTH_DELIMITER) === false) {
            return $array[$key] ?? $default;
        }

        foreach (\explode(static::DEPTH_DELIMITER, $key) as $segment) {
            if (! static::accessible($array) || ! static::exists($array, $segment)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }
}
