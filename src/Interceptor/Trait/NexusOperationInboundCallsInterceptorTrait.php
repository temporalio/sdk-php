<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use Temporal\Interceptor\NexusOperationInbound\NexusOperationCancelInput;
use Temporal\Interceptor\NexusOperationInbound\NexusOperationStartInput;
use Temporal\Interceptor\NexusOperationInboundCallsInterceptor;
use Temporal\Nexus\Handler\OperationStartResult;

/**
 * Trait that provides a default interceptor implementation.
 *
 * @see NexusOperationInboundCallsInterceptor
 */
trait NexusOperationInboundCallsInterceptorTrait
{
    /**
     * Default implementation of the `startNexusOperation` method.
     *
     * @see NexusOperationInboundCallsInterceptor::startNexusOperation()
     */
    public function startNexusOperation(NexusOperationStartInput $input, callable $next): OperationStartResult
    {
        return $next($input);
    }

    /**
     * Default implementation of the `cancelNexusOperation` method.
     *
     * @see NexusOperationInboundCallsInterceptor::cancelNexusOperation()
     */
    public function cancelNexusOperation(NexusOperationCancelInput $input, callable $next): void
    {
        $next($input);
    }
}
