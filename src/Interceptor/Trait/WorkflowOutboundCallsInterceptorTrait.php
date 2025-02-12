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
use Temporal\Interceptor\WorkflowOutboundCalls\UpsertMemoInput;
use Temporal\Interceptor\WorkflowOutboundCalls\UpsertSearchAttributesInput;
use Temporal\Interceptor\WorkflowOutboundCalls\UpsertTypedSearchAttributesInput;
use Temporal\Interceptor\WorkflowOutboundCallsInterceptor;

/**
 * Trait that provides a default interceptor implementation.
 *
 * @see WorkflowOutboundCallsInterceptor
 */
trait WorkflowOutboundCallsInterceptorTrait
{
    /**
     * Default implementation of the `executeActivity` method.
     *
     * @see WorkflowOutboundCallsInterceptor::executeActivity()
     */
    public function executeActivity(
        ExecuteActivityInput $input,
        callable $next,
    ): PromiseInterface {
        return $next($input);
    }

    /**
     * Default implementation of the `executeLocalActivity` method.
     *
     * @see WorkflowOutboundCallsInterceptor::executeLocalActivity()
     */
    public function executeLocalActivity(ExecuteLocalActivityInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `executeChildWorkflow` method.
     *
     * @see WorkflowOutboundCallsInterceptor::executeChildWorkflow()
     */
    public function executeChildWorkflow(ExecuteChildWorkflowInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `signalExternalWorkflow` method.
     *
     * @see WorkflowOutboundCallsInterceptor::signalExternalWorkflow()
     */
    public function signalExternalWorkflow(SignalExternalWorkflowInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `cancelExternalWorkflow` method.
     *
     * @see WorkflowOutboundCallsInterceptor::cancelExternalWorkflow()
     */
    public function cancelExternalWorkflow(CancelExternalWorkflowInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `sideEffect` method.
     *
     * @see WorkflowOutboundCallsInterceptor::sideEffect()
     */
    public function sideEffect(SideEffectInput $input, callable $next): mixed
    {
        return $next($input);
    }

    /**
     * Default implementation of the `timer` method.
     *
     * @see WorkflowOutboundCallsInterceptor::timer()
     */
    public function timer(TimerInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `panic` method.
     *
     * @see WorkflowOutboundCallsInterceptor::panic()
     */
    public function panic(PanicInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `complete` method.
     *
     * @see WorkflowOutboundCallsInterceptor::complete()
     */
    public function complete(CompleteInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `continueAsNew` method.
     *
     * @see WorkflowOutboundCallsInterceptor::continueAsNew()
     */
    public function continueAsNew(ContinueAsNewInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `getVersion` method.
     *
     * @see WorkflowOutboundCallsInterceptor::getVersion()
     */
    public function getVersion(GetVersionInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `upsertMemo` method.
     *
     * @see WorkflowOutboundCallsInterceptor::upsertMemo()
     */
    public function upsertMemo(UpsertMemoInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `upsertSearchAttributes` method.
     *
     * @see WorkflowOutboundCallsInterceptor::upsertSearchAttributes()
     */
    public function upsertSearchAttributes(UpsertSearchAttributesInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `upsertTypedSearchAttributes` method.
     *
     * @see WorkflowOutboundCallsInterceptor::upsertTypedSearchAttributes()
     */
    public function upsertTypedSearchAttributes(
        UpsertTypedSearchAttributesInput $input,
        callable $next,
    ): PromiseInterface {
        return $next($input);
    }

    /**
     * Default implementation of the `await` method.
     *
     * @see WorkflowOutboundCallsInterceptor::await()
     */
    public function await(AwaitInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }

    /**
     * Default implementation of the `awaitWithTimeout` method.
     *
     * @see WorkflowOutboundCallsInterceptor::awaitWithTimeout()
     */
    public function awaitWithTimeout(AwaitWithTimeoutInput $input, callable $next): PromiseInterface
    {
        return $next($input);
    }
}
