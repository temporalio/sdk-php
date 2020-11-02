<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

class Loop
{
    /** @var array */
    public static $onTick = [];

    /**
     * Register tick handler.
     *
     * @param callable $callable
     */
    public static function onTick(callable $callable)
    {
        self::$onTick = $callable;
    }

    /**
     * Finish tick.
     */
    public static function next()
    {
        foreach (self::$onTick as $item) {
            $item();
        }

        self::$onTick = [];
    }
}
