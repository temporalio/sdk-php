<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use Temporal\Interceptor\NexusOperationInbound\CancelOperationInput;
use Temporal\Interceptor\NexusOperationInbound\StartOperationInput;
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
     * Default implementation of the `startOperation` method.
     *
     * @see NexusOperationInboundCallsInterceptor::startOperation()
     */
    public function startOperation(StartOperationInput $input, callable $next): OperationStartResult
    {
        return $next($input);
    }

    /**
     * Default implementation of the `cancelOperation` method.
     *
     * @see NexusOperationInboundCallsInterceptor::cancelOperation()
     */
    public function cancelOperation(CancelOperationInput $input, callable $next): void
    {
        $next($input);
    }
}
