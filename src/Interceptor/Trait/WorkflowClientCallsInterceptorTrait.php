<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\Trait;

use Temporal\Client\Update\UpdateHandle;
use Temporal\Client\Workflow\WorkflowExecutionDescription;
use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\DescribeInput;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\QueryInput;
use Temporal\Interceptor\WorkflowClient\SignalInput;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\StartUpdateOutput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Interceptor\WorkflowClient\UpdateInput;
use Temporal\Interceptor\WorkflowClient\UpdateWithStartInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Workflow\WorkflowExecution;

/**
 * Trait that provides a default interceptor implementation.
 *
 * @see WorkflowClientCallsInterceptor
 */
trait WorkflowClientCallsInterceptorTrait
{
    /**
     * Default implementation of the `start` method.
     *
     * @see WorkflowClientCallsInterceptor::start()
     */
    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        return $next($input);
    }

    /**
     * Default implementation of the `signal` method.
     *
     * @see WorkflowClientCallsInterceptor::signal()
     */
    public function signal(SignalInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * Default implementation of the `update` method.
     *
     * @see WorkflowClientCallsInterceptor::update()
     */
    public function update(UpdateInput $input, callable $next): StartUpdateOutput
    {
        return $next($input);
    }

    /**
     * Default implementation of the `signalWithStart` method.
     *
     * @see WorkflowClientCallsInterceptor::signalWithStart()
     */
    public function signalWithStart(SignalWithStartInput $input, callable $next): WorkflowExecution
    {
        return $next($input);
    }

    /**
     * Default implementation of the `updateWithStart` method.
     *
     * @see WorkflowClientCallsInterceptor::updateWithStart()
     */
    public function updateWithStart(UpdateWithStartInput $input, callable $next): UpdateHandle
    {
        return $next($input);
    }

    /**
     * Default implementation of the `getResult` method.
     *
     * @see WorkflowClientCallsInterceptor::getResult()
     */
    public function getResult(GetResultInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    /**
     * Default implementation of the `query` method.
     *
     * @see WorkflowClientCallsInterceptor::query()
     */
    public function query(QueryInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    /**
     * Default implementation of the `cancel` method.
     *
     * @see WorkflowClientCallsInterceptor::cancel()
     */
    public function cancel(CancelInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * Default implementation of the `terminate` method.
     *
     * @see WorkflowClientCallsInterceptor::terminate()
     */
    public function terminate(TerminateInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * Default implementation of the `describe` method.
     *
     * @see WorkflowClientCallsInterceptor::describe()
     */
    public function describe(DescribeInput $input, callable $next): WorkflowExecutionDescription
    {
        return $next($input);
    }
}
