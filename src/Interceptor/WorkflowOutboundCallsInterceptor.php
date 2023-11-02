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
use Temporal\Internal\Interceptor\Interceptor;

/**
 * Interceptor for outbound workflow calls.
 *
 * It's recommended to use {@see WorkflowOutboundCallsInterceptorTrait} when implementing this interface because
 * the interface might be extended in the future. The trait will provide forward compatibility.
 *
 * @psalm-immutable
 */
interface WorkflowOutboundCallsInterceptor extends Interceptor
{
    /**
     * @param ExecuteActivityInput $input
     * @param callable(ExecuteActivityInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function executeActivity(
        ExecuteActivityInput $input,
        callable $next,
    ): PromiseInterface;

    /**
     * @param ExecuteLocalActivityInput $input
     * @param callable(ExecuteLocalActivityInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function executeLocalActivity(ExecuteLocalActivityInput $input, callable $next): PromiseInterface;

    /**
     * @param ExecuteChildWorkflowInput $input
     * @param callable(ExecuteChildWorkflowInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function executeChildWorkflow(ExecuteChildWorkflowInput $input, callable $next): PromiseInterface;

    /**
     * @param SignalExternalWorkflowInput $input
     * @param callable(SignalExternalWorkflowInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function signalExternalWorkflow(SignalExternalWorkflowInput $input, callable $next): PromiseInterface;

    /**
     * @param CancelExternalWorkflowInput $input
     * @param callable(CancelExternalWorkflowInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function cancelExternalWorkflow(CancelExternalWorkflowInput $input, callable $next): PromiseInterface;

    /**
     * Intercept {@see SideEffectInput::$callable} execution.
     *
     * @param SideEffectInput $input
     * @param callable(SideEffectInput): mixed $next
     *
     * @return mixed The result of the callable execution.
     */
    public function sideEffect(SideEffectInput $input, callable $next): mixed;

    /**
     * @param TimerInput $input
     * @param callable(TimerInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function timer(TimerInput $input, callable $next): PromiseInterface;

    /**
     * @param PanicInput $input
     * @param callable(PanicInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function panic(PanicInput $input, callable $next): PromiseInterface;

    /**
     * @param CompleteInput $input
     * @param callable(CompleteInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function complete(CompleteInput $input, callable $next): PromiseInterface;

    /**
     * @param ContinueAsNewInput $input
     * @param callable(ContinueAsNewInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function continueAsNew(ContinueAsNewInput $input, callable $next): PromiseInterface;

    /**
     * @param GetVersionInput $input
     * @param callable(GetVersionInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function getVersion(GetVersionInput $input, callable $next): PromiseInterface;

    /**
     * @param UpsertSearchAttributesInput $input
     * @param callable(UpsertSearchAttributesInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function upsertSearchAttributes(UpsertSearchAttributesInput $input, callable $next): PromiseInterface;

    /**
     * @param AwaitInput $input
     * @param callable(AwaitInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function await(AwaitInput $input, callable $next): PromiseInterface;

    /**
     * @param AwaitWithTimeoutInput $input
     * @param callable(AwaitWithTimeoutInput): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    public function awaitWithTimeout(AwaitWithTimeoutInput $input, callable $next): PromiseInterface;
}
