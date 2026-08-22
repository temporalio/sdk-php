<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

use React\Promise\PromiseInterface;
use Temporal\Exception\InvalidSuspendException;
use Temporal\Internal\Workflow\WorkflowContext;
use Temporal\Workflow;

/**
 * Bridges React promises to workflow Fibers managed by {@see Scope}.
 *
 * @internal
 * @psalm-internal Temporal
 */
final class Awaiter
{
    /** @var \WeakMap<\Fiber, true>|null */
    private static ?\WeakMap $managedFibers = null;

    private function __construct() {}

    /**
     * @template T
     * @param PromiseInterface<T> $promise
     * @return T
     */
    public static function await(PromiseInterface $promise, bool $interruptOnCancel = true): mixed
    {
        self::assertManaged();

        $context = Workflow::getCurrentContext();

        try {
            /** @var T $result */
            $result = \Fiber::suspend(new FiberSuspension($promise, $interruptOnCancel));
            return $result;
        } finally {
            Workflow::setCurrentContext($context);
        }
    }

    public static function assertManaged(): void
    {
        $fiber = \Fiber::getCurrent();

        if ($fiber === null || self::$managedFibers === null || !isset(self::$managedFibers[$fiber])) {
            $context = Workflow::getCurrentContext();

            if ($context instanceof WorkflowContext) {
                $context->assertWritable();
            }

            throw new InvalidSuspendException(
                'Temporal workflow APIs that suspend execution can only be called inside a managed workflow Fiber. '
                . 'This one was called from a promise callback or another unmanaged context.',
            );
        }
    }

    public static function isManaged(): bool
    {
        $fiber = \Fiber::getCurrent();

        return $fiber !== null && self::$managedFibers !== null && isset(self::$managedFibers[$fiber]);
    }

    public static function register(\Fiber $fiber): void
    {
        if (self::$managedFibers === null) {
            /** @var \WeakMap<\Fiber, true> $fibers */
            $fibers = new \WeakMap();
            self::$managedFibers = $fibers;
        }

        self::$managedFibers[$fiber] = true;
    }

    public static function unregister(\Fiber $fiber): void
    {
        if (self::$managedFibers !== null) {
            unset(self::$managedFibers[$fiber]);
        }
    }
}
