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
use Temporal\Internal\Transport\Request\ContinueAsNew;
use Temporal\Internal\Transport\Request\ExecuteActivity;
use Temporal\Internal\Transport\Request\ExecuteChildWorkflow;
use Temporal\Internal\Transport\Request\ExecuteLocalActivity;
use Temporal\Internal\Transport\Request\GetVersion;
use Temporal\Internal\Transport\Request\NewTimer;
use Temporal\Internal\Transport\Request\Panic;
use Temporal\Internal\Transport\Request\SignalExternalWorkflow;
use Temporal\Worker\Transport\Command\RequestInterface;


/**
 * Interceptor for outbound workflow requests.
 * Override existing methods to intercept and modify requests.
 */
abstract class WorkflowOutboundInterceptor implements WorkflowOutboundRequestInterceptor
{
    final public function handleOutboundRequest(RequestInterface $request, callable $next): PromiseInterface
    {
        return match ($request::class) {
            ExecuteActivity::class => $this->executeActivity($request, $next),
            ExecuteLocalActivity::class => $this->executeLocalActivity($request, $next),
            ExecuteChildWorkflow::class => $this->executeChildWorkflow($request, $next),
            ContinueAsNew::class => $this->continueAsNew($request, $next),
            NewTimer::class => $this->newTimer($request, $next),
            SignalExternalWorkflow::class => $this->signalExternalWorkflow($request, $next),
            GetVersion::class => $this->getVersion($request, $next),
            Panic::class => $this->panic($request, $next),
            default => $next($request),
        };
    }

    /**
     * @param ExecuteActivity $request
     * @param callable(ExecuteActivity): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function executeActivity(ExecuteActivity $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param ExecuteLocalActivity $request
     * @param callable(ExecuteLocalActivity): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function executeLocalActivity(ExecuteLocalActivity $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param ExecuteChildWorkflow $request
     * @param callable(ExecuteChildWorkflow): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function executeChildWorkflow(ExecuteChildWorkflow $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param NewTimer $request
     * @param callable(NewTimer): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function newTimer(NewTimer $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param ContinueAsNew $request
     * @param callable(ContinueAsNew): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function continueAsNew(ContinueAsNew $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param SignalExternalWorkflow $request
     * @param callable(SignalExternalWorkflow): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function signalExternalWorkflow(SignalExternalWorkflow $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param GetVersion $request
     * @param callable(GetVersion): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function getVersion(GetVersion $request, callable $next): PromiseInterface
    {
        return $next($request);
    }

    /**
     * @param Panic $request
     * @param callable(Panic): PromiseInterface $next
     *
     * @return PromiseInterface
     */
    protected function panic(Panic $request, callable $next): PromiseInterface
    {
        return $next($request);
    }
}
