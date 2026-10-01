<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Support;

use Temporal\Exception\OutOfContextException;

abstract class Facade
{
    /**
     * @var string
     */
    private const ERROR_NO_CONTEXT =
        'Calling facade methods can only be made ' .
        'from the currently running process';

    private static ?object $ctx = null;

    /** @var \WeakMap<\Fiber, array{?object}>|null */
    private static ?\WeakMap $fiberCtx = null;

    /**
     * Facade constructor.
     */
    private function __construct()
    {
        // Unable to create new facade instance
    }

    /**
     * @internal
     */
    public static function setCurrentContext(?object $ctx): void
    {
        $fiber = self::isolatedFiber();
        if ($fiber === null) {
            self::$ctx = $ctx;
            return;
        }

        self::$fiberCtx[$fiber] = [$ctx];
    }

    public static function getCurrentContext(): ?object
    {
        $fiber = self::isolatedFiber();
        if ($fiber === null) {
            return self::$ctx;
        }

        return self::$fiberCtx[$fiber][0];
    }

    /**
     * @internal
     */
    public static function isolateFiber(\Fiber $fiber): void
    {
        self::$fiberCtx ??= new \WeakMap();
        self::$fiberCtx[$fiber] = [null];
    }

    /**
     * Runs a callback with the given context installed, restoring the previous one afterwards.
     *
     * @internal
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function usingContext(?object $ctx, callable $callback): mixed
    {
        $saved = self::getCurrentContext();
        self::setCurrentContext($ctx);

        try {
            return $callback();
        } finally {
            self::setCurrentContext($saved);
        }
    }

    /**
     * @throws OutOfContextException
     */
    public static function getContextId(): int
    {
        $context = static::getCurrentContext();
        if ($context === null) {
            throw new \RuntimeException(self::ERROR_NO_CONTEXT);
        }

        return \spl_object_id($context);
    }

    /**
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $context = self::getCurrentContext();

        return $context->$name(...$arguments);
    }

    private static function isolatedFiber(): ?\Fiber
    {
        $fiber = \Fiber::getCurrent();

        return $fiber !== null && self::$fiberCtx?->offsetExists($fiber) ? $fiber : null;
    }
}
