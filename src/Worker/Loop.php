<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Temporal\Client\Worker;

/**
 * TODO Rewrite me
 */
class Loop
{
    public const ON_SIGNAL   = Event::ON_SIGNAL;
    public const ON_CALLBACK = Event::ON_CALLBACK;
    public const ON_TICK     = Event::ON_TICK;

    /** @var array */
    public static array $handlers = [
        self::ON_SIGNAL   => [],
        self::ON_CALLBACK => [],
        self::ON_TICK     => [],
    ];

    /**
     * @param callable $callable
     * @param string $level
     */
    public static function onTick(callable $callable, string $level = self::ON_TICK)
    {
        self::$handlers[$level][] = $callable;
    }

    /**
     * Finish tick.
     */
    public static function next(): void
    {
        while (! empty(self::$handlers[self::ON_SIGNAL])) {
            $item = array_shift(self::$handlers[self::ON_SIGNAL]);
            $item();
        }

        while (! empty(self::$handlers[self::ON_CALLBACK])) {
            $item = array_shift(self::$handlers[self::ON_CALLBACK]);
            $item();
        }

        while (! empty(self::$handlers[self::ON_TICK])) {
            $item = array_shift(self::$handlers[self::ON_TICK]);
            $item();
        }
    }
}
