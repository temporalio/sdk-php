<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Internal\Nexus\NexusContext;
use Temporal\Internal\Support\Facade;
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
 * Like {@see \Temporal\Workflow} and {@see \Temporal\Activity}, this facade extends
 * {@see Facade} and stores the dispatch state in the shared single-slot context.
 * The state is process-global and assumes a single active dispatch — running two
 * Nexus dispatches concurrently in the same process will trample each other.
 *
 * @since Nexus support
 */
final class Nexus extends Facade
{
    /**
     * Temporal-side context (namespace, taskQueue, workflowClient).
     *
     * @throws \LogicException when called outside a Nexus operation dispatch.
     */
    public static function getOperationContext(): NexusOperationContext
    {
        return self::getDispatchContext()?->operation ?? throw new \LogicException(
            'Nexus::getOperationContext() called outside a Nexus handler.',
        );
    }

    /**
     * Handler-side {@see OperationContext} for the current dispatch (links, headers,
     * deadline, service definition).
     *
     * @throws \LogicException when called outside a Nexus operation dispatch.
     */
    public static function getCurrentContext(): OperationContext
    {
        return self::getDispatchContext()?->current ?? throw new \LogicException(
            'Nexus::getCurrentContext() called outside a Nexus operation dispatch.',
        );
    }

    /**
     * Per-start details (requestId, callbackUrl, callbackHeaders, caller links).
     *
     * @throws \LogicException when called outside a start-operation dispatch.
     */
    public static function getStartDetails(): OperationStartDetails
    {
        return self::getDispatchContext()?->startDetails ?? throw new \LogicException(
            'Nexus::getStartDetails() called outside a start-operation dispatch.',
        );
    }

    /**
     * @throws \LogicException when called outside a cancel-operation dispatch.
     */
    public static function getCancelDetails(): OperationCancelDetails
    {
        return self::getDispatchContext()?->cancelDetails ?? throw new \LogicException(
            'Nexus::getCancelDetails() called outside a cancel-operation dispatch.',
        );
    }

    /**
     * Returns the composite dispatch state stored in the Facade slot, or null
     * when no Nexus dispatch is active.
     *
     * @internal Plumbing for {@see \Temporal\Internal\Nexus\NexusTaskHandler}
     *           and {@see \Temporal\Nexus\Handler\ServiceHandler}. Use the
     *           typed accessors above from user code.
     */
    public static function getDispatchContext(): ?NexusContext
    {
        $ctx = parent::getCurrentContext();
        return $ctx instanceof NexusContext ? $ctx : null;
    }
}
