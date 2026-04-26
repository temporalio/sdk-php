<?php

declare(strict_types=1);

namespace Temporal\Nexus;

/**
 * Static accessor for the {@see NexusOperationContext} active inside a
 * Nexus handler call.
 *
 * Mirrors Java `io.temporal.nexus.Nexus`. Java uses a thread-local; PHP
 * RoadRunner workers are single-threaded, so a process-static is sufficient
 * and equivalent.
 *
 * @since Nexus support
 */
final class Nexus
{
    private static ?NexusOperationContext $current = null;

    /**
     * Returns the context active for the current operation invocation.
     *
     * @throws \LogicException when called outside a Nexus operation dispatch
     *         (the handler infrastructure forgot to set the context, or the
     *         method was called from an unrelated code path).
     */
    public static function getOperationContext(): NexusOperationContext
    {
        if (self::$current === null) {
            throw new \LogicException(
                'Nexus::getOperationContext() called outside a Nexus handler. '
                . 'This accessor is only valid while NexusTaskHandler is dispatching '
                . 'a start/cancel operation.',
            );
        }
        return self::$current;
    }

    /**
     * @internal Plumbing for {@see \Temporal\Internal\Nexus\NexusTaskHandler}.
     *           Set the context before invoking the handler; clear (pass `null`)
     *           in a `finally` block.
     */
    public static function setCurrent(?NexusOperationContext $context): void
    {
        self::$current = $context;
    }
}
