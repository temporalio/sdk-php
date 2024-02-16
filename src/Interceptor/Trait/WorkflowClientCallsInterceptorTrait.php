<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\QueryInput;
use Temporal\Interceptor\WorkflowClient\SignalInput;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\StartUpdateOutput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Interceptor\WorkflowClient\UpdateInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Workflow\WorkflowExecution;

/**
 * Implements {@see WorkflowClientCallsInterceptor}
 */
trait WorkflowClientCallsInterceptorTrait
{
    /**
     * @see WorkflowClientCallsInterceptor::start()
     */
    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        return $next($input);
    }

    /**
     * @see WorkflowClientCallsInterceptor::signal()
     */
    public function signal(SignalInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @see WorkflowClientCallsInterceptor::update()
     */
    public function update(UpdateInput $input, callable $next): StartUpdateOutput
    {
        return $next($input);
    }

    /**
     * @see WorkflowClientCallsInterceptor::signalWithStart()
     */
    public function signalWithStart(SignalWithStartInput $input, callable $next): WorkflowExecution
    {
        return $next($input);
    }

    /**
     * @see WorkflowClientCallsInterceptor::getResult()
     */
    public function getResult(GetResultInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    /**
     * @see WorkflowClientCallsInterceptor::query()
     */
    public function query(QueryInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    /**
     * @see WorkflowClientCallsInterceptor::cancel()
     */
    public function cancel(CancelInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @see WorkflowClientCallsInterceptor::terminate()
     */
    public function terminate(TerminateInput $input, callable $next): void
    {
        $next($input);
    }
}
