<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use React\Promise\PromiseInterface;
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
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;

/**
 * Implements {@see WorkflowOutboundCallsInterceptor}
 */
trait WorkflowOutboundCallsInterceptorTrait
{
    /**
     * @see WorkflowOutboundCallsInterceptor::executeActivity()
     */
    public function executeActivity(
        ExecuteActivityInput $input,
        callable $next,
    ): PromiseInterface {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::executeLocalActivity()
     */
    public function executeLocalActivity(ExecuteLocalActivityInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::executeChildWorkflow()
     */
    public function executeChildWorkflow(ExecuteChildWorkflowInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::signalExternalWorkflow()
     */
    public function signalExternalWorkflow(SignalExternalWorkflowInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::cancelExternalWorkflow()
     */
    public function cancelExternalWorkflow(CancelExternalWorkflowInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::sideEffect()
     */
    public function sideEffect(SideEffectInput $input, callable $next): mixed
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::timer()
     */
    public function timer(TimerInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::panic()
     */
    public function panic(PanicInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::complete()
     */
    public function complete(CompleteInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::continueAsNew()
     */
    public function continueAsNew(ContinueAsNewInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::getVersion()
     */
    public function getVersion(GetVersionInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::upsertSearchAttributes()
     */
    public function upsertSearchAttributes(UpsertSearchAttributesInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::await()
     */
    public function await(AwaitInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * @see WorkflowOutboundCallsInterceptor::awaitWithTimeout()
     */
    public function awaitWithTimeout(AwaitWithTimeoutInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }
}
