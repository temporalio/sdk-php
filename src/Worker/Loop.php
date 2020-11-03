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
    public const ON_SIGNAL   = 0;
    public const ON_CALLBACK = 1;
    public const ON_TICK     = 2;

    /** @var array */
    public static $handlers = [
        self::ON_SIGNAL   => [],
        self::ON_CALLBACK => [],
        self::ON_TICK     => [],
    ];

    /**
     * @param callable $callable
     * @param int      $level
     */
    public static function onTick(callable $callable, int $level = self::ON_TICK)
    {
        self::$handlers[$level][] = $callable;
    }

    /**
     * Finish tick.
     */
    public static function next()
    {
        while (!empty(self::$handlers[self::ON_SIGNAL])) {
            $item = array_shift(self::$handlers[self::ON_SIGNAL]);
            $item();
        }

        while (!empty(self::$handlers[self::ON_CALLBACK])) {
            $item = array_shift(self::$handlers[self::ON_CALLBACK]);
            $item();
        }

        while (!empty(self::$handlers[self::ON_TICK])) {
            $item = array_shift(self::$handlers[self::ON_TICK]);
            $item();
        }
    }
}
