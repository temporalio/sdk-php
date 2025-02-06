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
use Temporal\Interceptor\Trait\WorkflowOutboundCallsInterceptorTrait;
use Temporal\Interceptor\WorkflowOutboundCalls\AwaitInput;
use Temporal\Interceptor\WorkflowOutboundCalls\AwaitWithTimeoutInput;
use Temporal\Interceptor\WorkflowOutboundCalls\CancelExternalWorkflowInput;
use Temporal\Interceptor\WorkflowOutboundCalls\CompleteInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ContinueAsNewInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteActivityInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteChildWorkflowInput;
use Temporal\Interceptor\WorkflowOutboundCalls\ExecuteLocalActivityInput;
use Temporal\Interceptor\WorkflowOutboundCalls\GetVersionInput;
use Temporal\Interceptor\WorkflowOutboundCalls\PanicInput;
use Temporal\Interceptor\WorkflowOutboundCalls\SideEffectInput;
use Temporal\Interceptor\WorkflowOutboundCalls\SignalExternalWorkflowInput;
use Temporal\Interceptor\WorkflowOutboundCalls\TimerInput;
use Temporal\Interceptor\WorkflowOutboundCalls\UpsertSearchAttributesInput;
use Temporal\Interceptor\WorkflowOutboundCalls\UpsertTypedSearchAttributesInput;
use Temporal\Internal\Interceptor\Interceptor;

/**
 * Interceptor for outbound workflow calls.
 *
 * It's recommended to use {@see WorkflowOutboundCallsInterceptorTrait} when implementing this interface because
 * the interface might be extended in the future. The trait will provide forward compatibility.
 *
 * ```php
 * class MyWorkflowOutboundCallsInterceptor implements WorkflowOutboundCallsInterceptor
 * {
 *     use WorkflowOutboundCallsInterceptorTrait;
 *
 *     public function executeActivity(
 *         ExecuteActivityInput $input,
 *         callable $next,
 *     ): PromiseInterface {
 *         error_log('Calling activity: ' . $input->type);
 *
 *         return $next($input);
 *     }
 * }
 * ```
 */
interface WorkflowOutboundCallsInterceptor extends Interceptor
{
    /**
     * @param callable(ExecuteActivityInput): PromiseInterface $next
     */
    public function executeActivity(ExecuteActivityInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(ExecuteLocalActivityInput): PromiseInterface $next
     */
    public function executeLocalActivity(ExecuteLocalActivityInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(ExecuteChildWorkflowInput): PromiseInterface $next
     */
    public function executeChildWorkflow(ExecuteChildWorkflowInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(SignalExternalWorkflowInput): PromiseInterface $next
     */
    public function signalExternalWorkflow(SignalExternalWorkflowInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(CancelExternalWorkflowInput): PromiseInterface $next
     */
    public function cancelExternalWorkflow(CancelExternalWorkflowInput $input, callable $next): PromiseInterface;

    /**
     * Intercept {@see SideEffectInput::$callable} execution.
     *
     * @param callable(SideEffectInput): mixed $next
     *
     * @return mixed The result of the callable execution.
     */
    public function sideEffect(SideEffectInput $input, callable $next): mixed;

    /**
     * @param callable(TimerInput): PromiseInterface $next
     */
    public function timer(TimerInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(PanicInput): PromiseInterface $next
     */
    public function panic(PanicInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(CompleteInput): PromiseInterface $next
     */
    public function complete(CompleteInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(ContinueAsNewInput): PromiseInterface $next
     */
    public function continueAsNew(ContinueAsNewInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(GetVersionInput): PromiseInterface $next
     */
    public function getVersion(GetVersionInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(UpsertSearchAttributesInput): PromiseInterface $next
     */
    public function upsertSearchAttributes(UpsertSearchAttributesInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(UpsertTypedSearchAttributesInput): PromiseInterface $next
     */
    public function upsertTypedSearchAttributes(
        UpsertTypedSearchAttributesInput $input,
        callable $next,
    ): PromiseInterface;

    /**
     * @param callable(AwaitInput): PromiseInterface $next
     */
    public function await(AwaitInput $input, callable $next): PromiseInterface;

    /**
     * @param callable(AwaitWithTimeoutInput): PromiseInterface $next
     */
    public function awaitWithTimeout(AwaitWithTimeoutInput $input, callable $next): PromiseInterface;
}
