<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Nexus;

use Temporal\Interceptor\NexusOperationOutbound\GetInfoInput;
use Temporal\Internal\Nexus\NexusContext;
use Temporal\Internal\Nexus\NexusEnvironment;
use Temporal\Internal\Support\Facade;
use Temporal\Nexus\Handler\OperationCancelDetails;
use Temporal\Nexus\Handler\OperationContext;
use Temporal\Nexus\Handler\OperationStartDetails;

/**
 * Static accessors for the active Nexus dispatch state. Use these inside operation method bodies instead of threading the context through parameters.
 */
final class Nexus extends Facade
{
    /**
     * Guard accessor for the active Nexus dispatch context.
     *
     * Mirrors {@see \Temporal\Workflow::getCurrentContext()}: returns the composite
     * {@see NexusContext} for the current dispatch and throws when called outside a
     * Nexus operation handler. The handler-side {@see Handler\OperationContext}
     * (links, headers, deadline, service definition) is reachable via
     * {@see NexusContext::$current}.
     *
     * @throws \LogicException when called outside a Nexus operation dispatch.
     */
    public static function getCurrentContext(): NexusContext
    {
        $context = parent::getCurrentContext();
        if (!$context instanceof NexusContext) {
            throw new \LogicException('The Nexus facade can be used only inside a Nexus operation handler.');
        }

        return $context;
    }

    /**
     * Handler-side {@see OperationContext} for the current dispatch (links, headers,
     * deadline, service definition). Guarded accessor for {@see NexusContext::$current}.
     *
     * @throws \LogicException when called outside a Nexus operation dispatch.
     */
    public static function getCurrentOperationContext(): OperationContext
    {
        return self::getCurrentContext()->current;
    }

    /**
     * Temporal-side context (namespace, taskQueue, workflowClient).
     *
     * @throws \LogicException when called outside a Nexus operation dispatch.
     */
    public static function getOperationContext(): NexusOperationContext
    {
        $dispatch = self::getCurrentContext();
        $info = $dispatch->operation ?? throw new \LogicException(
            'Nexus::getOperationContext() called outside a Nexus handler.',
        );

        $pipeline = $dispatch->outboundPipeline;
        if ($pipeline === null) {
            return $info;
        }

        return $pipeline->with(
            /**
             * @psalm-suppress UnusedClosureParam The terminal call ignores the empty input DTO.
             * @see \Temporal\Interceptor\NexusOperationOutboundCallsInterceptor::getInfo()
             */
            static fn(GetInfoInput $input): NexusOperationContext => $info,
            'getInfo',
        )(new GetInfoInput());
    }

    /**
     * Per-start details (requestId, callbackUrl, callbackHeaders, caller links).
     *
     * @throws \LogicException when called outside a start-operation dispatch.
     */
    public static function getStartDetails(): OperationStartDetails
    {
        return self::getCurrentContext()->startDetails ?? throw new \LogicException(
            'Nexus::getStartDetails() called outside a start-operation dispatch.',
        );
    }

    /**
     * @throws \LogicException when called outside a cancel-operation dispatch.
     */
    public static function getCancelDetails(): OperationCancelDetails
    {
        return self::getCurrentContext()->cancelDetails ?? throw new \LogicException(
            'Nexus::getCancelDetails() called outside a cancel-operation dispatch.',
        );
    }

    /**
     * Worker-bound execution environment backing Nexus async helpers
     * (carries the WorkflowClient, namespace, taskQueue).
     *
     * @internal Plumbing for {@see \Temporal\Nexus\WorkflowRunOperation}; user code
     *           should drive backing workflows through the helper API rather than
     *           reaching for the client directly.
     *
     * @throws \LogicException when called outside a Nexus operation dispatch.
     */
    public static function getEnvironment(): NexusEnvironment
    {
        return self::getCurrentContext()->environment ?? throw new \LogicException(
            'Nexus::getEnvironment() called outside a Nexus handler dispatch.',
        );
    }
}
