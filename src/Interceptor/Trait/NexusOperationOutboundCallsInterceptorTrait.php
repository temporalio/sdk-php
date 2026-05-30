<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use Temporal\Interceptor\NexusOperationOutbound\GetInfoInput;
use Temporal\Interceptor\NexusOperationOutboundCallsInterceptor;
use Temporal\Nexus\NexusOperationContext;

/**
 * Trait that provides a default interceptor implementation.
 *
 * @see NexusOperationOutboundCallsInterceptor
 */
trait NexusOperationOutboundCallsInterceptorTrait
{
    /**
     * Default implementation of the `getInfo` method.
     *
     * @see NexusOperationOutboundCallsInterceptor::getInfo()
     */
    public function getInfo(GetInfoInput $input, callable $next): NexusOperationContext
    {
        return $next($input);
    }
}
