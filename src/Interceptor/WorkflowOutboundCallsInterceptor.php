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
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteChildWorkflowInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteLocalActivityInput;
use Temporal\Interceptor\WorkflowOutboundCalls\SignalExternalWorkflowInput;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Internal\Transport\Request\CancelExternalWorkflow;
use Temporal\Internal\Transport\Request\ExecuteActivity;

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
     * @param ExecuteLocalActivityInput $request
     * @param callable(ExecuteLocalActivityInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function executeLocalActivity(ExecuteLocalActivityInput $request, callable $next): PromiseInterface;

    /**
     * @param ExecuteChildWorkflowInput $request
     * @param callable(ExecuteChildWorkflowInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function executeChildWorkflow(ExecuteChildWorkflowInput $request, callable $next): PromiseInterface;

    /**
     * @param SignalExternalWorkflowInput $request
     * @param callable(SignalExternalWorkflowInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function signalExternalWorkflow(SignalExternalWorkflowInput $request, callable $next): PromiseInterface;

    /**
     * @param CancelExternalWorkflow $request
     * @param callable(CancelExternalWorkflow): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function cancelExternalWorkflow(CancelExternalWorkflow $request, callable $next): PromiseInterface;
}
