<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\DataConverter\EncodedValues;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Internal\Support\DateInterval;

/**
 * WorkflowStub is a client side stub to a single workflow instance. It can be used to start,
 * signal, query, wait for completion and cancel a workflow execution. Created through {@link
 * WorkflowClient#newUntypedWorkflowStub(String, WorkflowOptions)} or {@link
 * WorkflowClient#newUntypedWorkflowStub(WorkflowExecution, Optional)}.
 * @psalm-import-type DateIntervalValue from DateInterval
 */
interface WorkflowStubInterface
{
    /**
     * @return WorkflowOptions
     */
    public function getOptions(): WorkflowOptions;

    /**
     * @return string
     */
    public function getWorkflowType(): string;

    /**
     * @return WorkflowExecution
     */
    public function getExecution(): WorkflowExecution;

    /**
     * @param string $name
     * @param array $args
     */
    public function signal(string $name, array $args = []): void;

    /**
     * Synchronously queries workflow by invoking its query handler. Usually a query handler is a
     * method annotated with {@link io.temporal.workflow.QueryMethod}.
     *
     * @param string $name
     * @param array $args
     * @return EncodedValues|null
     */
    public function query(string $name, array $args = []): ?EncodedValues;

    /**
     * Starts workflow execution without blocking the thread. Use getResult to wait for the workflow execution result.
     *
     * @param array $args
     * @return WorkflowExecution|null
     */
    public function start(array $args = []): ?WorkflowExecution;

    /**
     * @param string $signal
     * @param array $signalArgs
     * @param array $startArgs
     * @return WorkflowExecution
     */
    public function signalWithStart(
        string $signal,
        array $signalArgs = [],
        array $startArgs = []
    ): WorkflowExecution;

    /**
     * Returns workflow result potentially waiting for workflow to complete. Behind the scene this
     * call performs long poll on Temporal service waiting for workflow completion notification.
     *
     * @param DateIntervalValue|null $timeout
     * @param mixed $returnType
     * @return mixed
     * @see DateInterval
     */
    public function getResult($timeout = null, $returnType = null);

    /**
     * Request cancellation of a workflow execution.
     *
     * <p>Cancellation cancels {@link io.temporal.workflow.CancellationScope} that wraps the main
     * workflow method. Note that workflow can take long time to get canceled or even completely
     * ignore the cancellation request.
     *
     * @return void
     */
    public function cancel(): void;

    /**
     * Terminates a workflow execution.
     *
     * <p>Termination is a hard stop of a workflow execution which doesn't give workflow code any
     * chance to perform cleanup.
     *
     * @param string $reason
     * @param array $details
     * @return void
     */
    public function terminate(string $reason, array $details = []): void;
}
