<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Interceptor\NexusOperationOutbound\GetInfoInput;
use Temporal\Interceptor\Trait\NexusOperationOutboundCallsInterceptorTrait;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Nexus\NexusOperationContext;

/**
 * Intercepts calls a Nexus operation handler makes back into the Temporal APIs,
 * counterpart to Java's {@code NexusOperationOutboundCallsInterceptor}.
 *
 * Java additionally intercepts {@code getMetricsScope()} and {@code getWorkflowClient()};
 * neither has a clean PHP handler analogue (the metrics scope is RoadRunner-owned and the
 * WorkflowClient is kept on the internal channel), so only {@see self::getInfo()} is exposed.
 *
 * It's recommended to use `NexusOperationOutboundCallsInterceptorTrait` when implementing this
 * interface because the interface might be extended in the future. The trait will provide forward
 * compatibility.
 *
 * ```php
 * class MyNexusOperationOutboundCallsInterceptor implements NexusOperationOutboundCallsInterceptor
 * {
 *     use NexusOperationOutboundCallsInterceptorTrait;
 *
 *     public function getInfo(GetInfoInput $input, callable $next): NexusOperationContext
 *     {
 *         $info = $next($input);
 *         // observe namespace/taskQueue, e.g. attach to a span
 *         return $info;
 *     }
 * }
 * ```
 *
 * @see NexusOperationOutboundCallsInterceptorTrait
 */
interface NexusOperationOutboundCallsInterceptor extends Interceptor
{
    /**
     * Intercepts the call to read the Nexus operation info ({@see \Temporal\Nexus\Nexus::getOperationContext()}).
     *
     * @param callable(GetInfoInput): NexusOperationContext $next
     */
    public function getInfo(GetInfoInput $input, callable $next): NexusOperationContext;
}
