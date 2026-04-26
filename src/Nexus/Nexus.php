<?php

declare(strict_types=1);

namespace Temporal\Nexus;

/**
 * Static accessor for the active {@see NexusOperationContext}.
 *
 * @since Nexus support
 */
final class Nexus
{
    private static ?NexusOperationContext $current = null;

    /**
     * @throws \LogicException when called outside a Nexus operation dispatch.
     */
    public static function getOperationContext(): NexusOperationContext
    {
        return self::$current ?? throw new \LogicException(
            'Nexus::getOperationContext() called outside a Nexus handler.',
        );
    }

    /**
     * Same as {@see self::getOperationContext()} but returns null instead of throwing.
     */
    public static function tryGetOperationContext(): ?NexusOperationContext
    {
        return self::$current;
    }

    /**
     * @internal Plumbing for {@see \Temporal\Internal\Nexus\NexusTaskHandler}.
     */
    public static function setCurrent(?NexusOperationContext $context): void
    {
        self::$current = $context;
    }
}
