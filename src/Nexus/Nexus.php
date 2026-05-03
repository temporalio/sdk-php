<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;

/**
 * Static accessors for the active Nexus dispatch state.
 *
 * Inside an `#[Operation]` / `#[AsyncOperation]` / `#[OperationCancel]` method body
 * use the helpers below to reach the surrounding Nexus environment instead of
 * accepting it as parameters (the impl method signature must stay identical to
 * its contract method).
 *
 * @since Nexus support
 */
final class Nexus
{
    private static ?NexusOperationContext $current = null;
    private static ?OperationContext $operationContext = null;
    private static ?OperationStartDetails $startDetails = null;
    private static ?OperationCancelDetails $cancelDetails = null;

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
     * Handler-side {@see OperationContext} for the current dispatch (links, headers,
     * deadline, service definition).
     *
     * @throws \LogicException when called outside a Nexus operation dispatch.
     */
    public static function getCurrentContext(): OperationContext
    {
        return self::$operationContext ?? throw new \LogicException(
            'Nexus::getCurrentContext() called outside a Nexus operation dispatch.',
        );
    }

    public static function tryGetCurrentContext(): ?OperationContext
    {
        return self::$operationContext;
    }

    /**
     * Per-start details (requestId, callbackUrl, callbackHeaders, caller links).
     *
     * @throws \LogicException when called outside a start-operation dispatch.
     */
    public static function getStartDetails(): OperationStartDetails
    {
        return self::$startDetails ?? throw new \LogicException(
            'Nexus::getStartDetails() called outside a start-operation dispatch.',
        );
    }

    public static function tryGetStartDetails(): ?OperationStartDetails
    {
        return self::$startDetails;
    }

    /**
     * @throws \LogicException when called outside a cancel-operation dispatch.
     */
    public static function getCancelDetails(): OperationCancelDetails
    {
        return self::$cancelDetails ?? throw new \LogicException(
            'Nexus::getCancelDetails() called outside a cancel-operation dispatch.',
        );
    }

    public static function tryGetCancelDetails(): ?OperationCancelDetails
    {
        return self::$cancelDetails;
    }

    /**
     * @internal Plumbing for {@see \Temporal\Internal\Nexus\NexusTaskHandler}.
     */
    public static function setCurrent(?NexusOperationContext $context): void
    {
        self::$current = $context;
    }

    /**
     * @internal Plumbing for {@see \Temporal\Nexus\Handler\ServiceHandler}.
     */
    public static function setOperationContext(?OperationContext $context): void
    {
        self::$operationContext = $context;
    }

    /**
     * @internal Plumbing for {@see \Temporal\Nexus\Handler\ServiceHandler}.
     */
    public static function setStartDetails(?OperationStartDetails $details): void
    {
        self::$startDetails = $details;
    }

    /**
     * @internal Plumbing for {@see \Temporal\Nexus\Handler\ServiceHandler}.
     */
    public static function setCancelDetails(?OperationCancelDetails $details): void
    {
        self::$cancelDetails = $details;
    }
}
