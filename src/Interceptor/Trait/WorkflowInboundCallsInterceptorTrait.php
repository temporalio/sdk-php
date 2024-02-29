<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\UpdateInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundCallsInterceptor;

/**
 * Implements {@see WorkflowInboundCallsInterceptor}
 */
trait WorkflowInboundCallsInterceptorTrait
{
    /**
     * @see WorkflowInboundCallsInterceptor::execute()
     */
    public function execute(WorkflowInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @see WorkflowInboundCallsInterceptor::handleSignal()
     */
    public function handleSignal(SignalInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @see WorkflowInboundCallsInterceptor::handleQuery()
     */
    public function handleQuery(QueryInput $input, callable $next): mixed
    {
        return $next($input);
    }

    /**
     * @see WorkflowInboundCallsInterceptor::handleUpdate()
     */
    public function handleUpdate(UpdateInput $input, callable $next): mixed
    {
        return $next($input);
    }

    /**
     * @see WorkflowInboundCallsInterceptor::handleUpdate()
     */
    public function validateUpdate(UpdateInput $input, callable $next): void
    {
        $next($input);
    }
}
