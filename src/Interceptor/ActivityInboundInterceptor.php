<?php

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
