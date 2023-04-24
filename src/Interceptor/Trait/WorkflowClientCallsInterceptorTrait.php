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
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Workflow\WorkflowExecution;

/**
 * Implements {@see WorkflowClientCallsInterceptor}
 */
trait WorkflowClientCallsInterceptorTrait
{
    /**
     * @inheritDoc
     */
    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        return $next($input);
    }

    /**
     * @inheritDoc
     */
    public function signal(SignalInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @inheritDoc
     */
    public function signalWithStart(SignalWithStartInput $input, callable $next): WorkflowExecution
    {
        return $next($input);
    }

    /**
     * @inheritDoc
     */
    public function getResult(GetResultInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    /**
     * @inheritDoc
     */
    public function query(QueryInput $input, callable $next): ?EncodedValues
    {
        return $next($input);
    }

    /**
     * @inheritDoc
     */
    public function cancel(CancelInput $input, callable $next): void
    {
        $next($input);
    }

    /**
     * @inheritDoc
     */
    public function terminate(TerminateInput $input, callable $next): void
    {
        $next($input);
    }
}
