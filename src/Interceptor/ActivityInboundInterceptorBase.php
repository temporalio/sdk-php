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

abstract class ActivityInboundInterceptorBase implements \Temporal\Interceptor\ActivityInboundInterceptor
{
    /**
     * @inheritDoc
     */
    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        return $next($input);
    }
}
