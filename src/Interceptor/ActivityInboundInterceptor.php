<?php

declare(strict_types=1);

namespace Temporal\Interceptor;

interface ActivityInboundInterceptor extends Interceptor
{
    /**
     * @param callable(): mixed $next
     *
     * @return mixed
     */
    public function handleActivityInbound(callable $next): mixed;
}
