<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Activity\ActivityContextInterface;
use Temporal\Internal\Interceptor\Interceptor;

interface ActivityInboundInterceptor extends Interceptor
{
    /**
     * @param ActivityContextInterface $context
     * @param callable(ActivityContextInterface): mixed $next
     *
     * @return mixed
     */
    public function handleActivityInbound(ActivityContextInterface $context, callable $next): mixed;
}
