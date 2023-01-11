<?php

declare(strict_types=1);

namespace Temporal\Interceptor;

use React\Promise\PromiseInterface;
use Temporal\Worker\Transport\Command\RequestInterface;

interface WorkflowOutboundInterceptor extends Interceptor
{
    /**
     * @param RequestInterface $request
     * @param callable(RequestInterface $request): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface;
}
