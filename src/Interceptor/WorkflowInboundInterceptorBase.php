<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Interceptor\WorkflowInbound\QueryInput;
use Temporal\Interceptor\WorkflowInbound\SignalInput;
use Temporal\Interceptor\WorkflowInbound\WorkflowInput;

abstract class WorkflowInboundInterceptorBase implements \Temporal\Interceptor\WorkflowInboundInterceptor
{
    /**
     * @inheritDoc
     */
    public function execute(WorkflowInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @inheritDoc
     */
    public function handleSignal(SignalInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @inheritDoc
     */
    public function handleQuery(QueryInput $input, callable $next): mixed
    {
        return $next($input);
    }
}
