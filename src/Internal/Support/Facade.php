<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

/**
 * @template T of object
 */
abstract class Facade
{
    /**
     * @var string
     */
    private const ERROR_NO_CONTEXT =
        'Calling facade methods can only be made ' .
        'from the currently running process'
    ;

    /**
     * Facade constructor.
     */
    private function __construct()
    {
        // Unable to create new facade instance
    }

    /**
     * @var object<T>|null
     */
    private static ?object $ctx = null;

    /**
     * @param object<T>|null $ctx
     */
    public static function setCurrentContext(?object $ctx): void
    {
        self::$ctx = $ctx;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $context = self::getCurrentContext();

        return $context->$name(...$arguments);
    }

    /**
     * @return object<T>
     */
    public static function getCurrentContext(): object
    {
        if (self::$ctx === null) {
            throw new \RuntimeException(self::ERROR_NO_CONTEXT);
        }

        return self::$ctx;
    }

    /**
     * @return int
     */
    public static function getContextId(): int
    {
        if (self::$ctx === null) {
            throw new \RuntimeException(self::ERROR_NO_CONTEXT);
        }

        return \spl_object_id(self::$ctx);
    }
}
