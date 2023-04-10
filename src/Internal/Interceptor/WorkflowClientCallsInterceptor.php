<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Interceptor;

use Temporal\DataConverter\EncodedValues;
use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\QueryInput;
use Temporal\Interceptor\WorkflowClient\SignalInput;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
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
     * @param SignalWithStartInput $input
     * @param callable(SignalWithStartInput): WorkflowExecution $next
     *
     * @return WorkflowExecution
     */
    public function signalWithStart(SignalWithStartInput $input, callable $next): WorkflowExecution;

    /**
     * @param GetResultInput $input
     * @param callable(GetResultInput): ?EncodedValues $next
     *
     * @return EncodedValues|null
     */
    public function getResult(GetResultInput $input, callable $next): ?EncodedValues;

    /**
     * @param QueryInput $input
     * @param callable(QueryInput): ?EncodedValues $next
     *
     * @return EncodedValues|null
     */
    public function query(QueryInput $input, callable $next): ?EncodedValues;

    /**
     * @param CancelInput $input
     * @param callable(CancelInput): void $next
     *
     * @return void
     */
    public function cancel(CancelInput $input, callable $next): void;

    /**
     * @param TerminateInput $input
     * @param callable(TerminateInput): void $next
     *
     * @return void
     */
    public function terminate(TerminateInput $input, callable $next): void;
}
