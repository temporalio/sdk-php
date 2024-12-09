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
use Temporal\Internal\Transport\Request\Cancel;
use Temporal\Internal\Transport\Request\CancelExternalWorkflow;
use Temporal\Internal\Transport\Request\CompleteWorkflow;
use Temporal\Internal\Transport\Request\ContinueAsNew;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\ExecuteLocalActivity;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Internal\Transport\Request\Panic;
use Temporal\Internal\Transport\Request\SideEffect;
use Temporal\Internal\Transport\Request\SignalExternalWorkflow;
use Temporal\Internal\Transport\Request\UpsertSearchAttributes;
use Temporal\Worker\Transport\Command\RequestInterface;

/**
 * Trait that provides a default interceptor implementation.
 *
 * @see WorkflowOutboundRequestInterceptor
 */
trait WorkflowOutboundRequestInterceptorTrait
{
    /**
     * Default implementation of the `handleOutboundRequest` method.
     *
     * @see WorkflowOutboundRequestInterceptor::handleOutboundRequest()
     */
    final public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        return match ($request::class) {
            ExecuteActivity::class => $this->executeActivityRequest($request, $next),
            ExecuteLocalActivity::class => $this->executeLocalActivityRequest($request, $next),
            ExecuteChildWorkflow::class => $this->executeChildWorkflowRequest($request, $next),
            ContinueAsNew::class => $this->continueAsNewRequest($request, $next),
            NewTimer::class => $this->newTimerRequest($request, $next),
            CompleteWorkflow::class => $this->completeWorkflowRequest($request, $next),
            SignalExternalWorkflow::class => $this->signalExternalWorkflowRequest($request, $next),
            CancelExternalWorkflow::class => $this->cancelExternalWorkflowRequest($request, $next),
            GetVersion::class => $this->getVersionRequest($request, $next),
            Panic::class => $this->panicRequest($request, $next),
            SideEffect::class => $this->sideEffectRequest($request, $next),
            UpsertSearchAttributes::class => $this->upsertSearchAttributesRequest($request, $next),
            Cancel::class => $this->cancelRequest($request, $next),
            default => $next($request),
        };
    }

    /**
     * @param callable(ExecuteActivity): PromiseInterface $next
     *
     */
    private function executeActivityRequest(ExecuteActivity $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(ExecuteLocalActivity): PromiseInterface $next
     *
     */
    private function executeLocalActivityRequest(ExecuteLocalActivity $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(ExecuteChildWorkflow): PromiseInterface $next
     *
     */
    private function executeChildWorkflowRequest(ExecuteChildWorkflow $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(NewTimer): PromiseInterface $next
     *
     */
    private function newTimerRequest(NewTimer $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(ContinueAsNew): PromiseInterface $next
     *
     */
    private function continueAsNewRequest(ContinueAsNew $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(SignalExternalWorkflow): PromiseInterface $next
     *
     */
    private function signalExternalWorkflowRequest(SignalExternalWorkflow $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(CompleteWorkflow): PromiseInterface $next
     *
     */
    private function completeWorkflowRequest(CompleteWorkflow $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(CancelExternalWorkflow): PromiseInterface $next
     *
     */
    private function cancelExternalWorkflowRequest(CancelExternalWorkflow $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(GetVersion): PromiseInterface $next
     *
     */
    private function getVersionRequest(GetVersion $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(Panic): PromiseInterface $next
     *
     */
    private function panicRequest(Panic $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(SideEffect): PromiseInterface $next
     *
     */
    private function sideEffectRequest(SideEffect $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(UpsertSearchAttributes): PromiseInterface $next
     *
     */
    private function upsertSearchAttributesRequest(UpsertSearchAttributes $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param callable(Cancel): PromiseInterface $next
     *
     */
    private function cancelRequest(Cancel $request, callable $next): PromiseInterface
    {
        return $next($request);
    }
}
