<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use React\Promise\PromiseInterface;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteActivityInput;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\ExecuteLocalActivity;

/**
 * Interceptor for outbound workflow requests.
 * Override existing methods to intercept and modify requests.
 *
 * @psalm-immutable
 */
interface WorkflowOutboundCallsInterceptor extends Interceptor
{
    /**
     * @param ExecuteActivityInput $input
     * @param callable(ExecuteActivity): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function executeActivity(
        ExecuteActivityInput $input,
        callable $next,
    ): PromiseInterface;

    /**
     * @param ExecuteLocalActivity $request
     * @param callable(ExecuteLocalActivity): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function executeLocalActivity(ExecuteLocalActivity $request, callable $next): PromiseInterface;

    /**
     * @param ExecuteChildWorkflow $request
     * @param callable(ExecuteChildWorkflow): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function executeChildWorkflow(ExecuteChildWorkflow $request, callable $next): PromiseInterface;
}
