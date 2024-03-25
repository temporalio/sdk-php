<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;
use Temporal\Internal\Interceptor\Interceptor;

/**
 * It's recommended to use `ActivityInboundInterceptorTrait` when implementing this interface because
 * the interface might be extended in the future. The trait will provide forward compatibility.
 *
 * @see ActivityInboundInterceptorTrait
 */
interface ActivityInboundInterceptor extends Interceptor
{
    /**
     * Intercepts a call to the main activity entry method.
     *
     * @param ActivityInput $input
     * @param callable(ActivityInput): mixed $next
     *
     * @return mixed
     */
    public function handleActivityInbound(ActivityInput $input, callable $next): mixed;
}
