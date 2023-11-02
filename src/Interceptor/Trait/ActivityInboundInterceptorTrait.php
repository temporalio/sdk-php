<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;

/**
 * Implements {@see ActivityInboundInterceptor}
 */
trait ActivityInboundInterceptorTrait
{
    /**
     * @see ActivityInboundInterceptor::handleActivityInbound()
     */
    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        return $next($input);
    }
}
