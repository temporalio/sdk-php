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
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;
use Temporal\Interceptor\WorkflowInboundInterceptor;

/**
 * Implements {@see WorkflowInboundInterceptor}
 */
trait WorkflowInboundInterceptorTrait
{
    /**
     * @see WorkflowInboundInterceptor::execute()
     */
    public function execute(WorkflowInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @see WorkflowInboundInterceptor::handleSignal()
     */
    public function handleSignal(SignalInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @see WorkflowInboundInterceptor::handleQuery()
     */
    public function handleQuery(QueryInput $input, callable $next): mixed
    {
        return $next($input);
    }
}
