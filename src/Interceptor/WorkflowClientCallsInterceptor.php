<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor;

use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\QueryInput;
use Temporal\Interceptor\WorkflowClient\SignalInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Internal\Interceptor\Interceptor;
use Temporal\Workflow\WorkflowExecution;

interface WorkflowClientCallsInterceptor extends Interceptor
{
    /**
     * @param StartInput $input
     * @param callable(StartInput): WorkflowExecution $next
     *
     * @return WorkflowExecution
     */
    public function start(StartInput $input, callable $next): WorkflowExecution;

    /**
     * @param SignalInput $input
     * @param callable(SignalInput): void $next
     *
     * @return void
     */
    public function signal(SignalInput $input, callable $next): void;

    /**
     * @param QueryInput $input
     * @param callable(QueryInput): WorkflowExecution $next
     *
     * @return WorkflowExecution
     */
    public function signalWithStart(QueryInput $input, callable $next): WorkflowExecution;

    // todo
    public function getResult(QueryInput $input, callable $next): mixed;

    // WorkflowExecutionStatus + result
    public function query(QueryInput $input, callable $next): mixed;

    public function cancel(CancelInput $input, callable $next): void;

    public function terminate(TerminateInput $input, callable $next): void;
}