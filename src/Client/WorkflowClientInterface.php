<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Temporal\Api\Enums\V1\HistoryEventFilterType;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowExecutionInfo as WorkflowExecutionInfoDto;
use Temporal\Workflow\WorkflowRunInterface;

interface WorkflowClientInterface
{
    /**
     * @return ServiceClientInterface
     */
    public function getServiceClient(): ServiceClientInterface;

    /**
     * Starts untyped and typed workflow stubs in async mode.
     *
     * @param WorkflowStubInterface|object $workflow
     * @param mixed $args
     * @return WorkflowRunInterface
     */
    public function start($workflow, ...$args): WorkflowRunInterface;

    /**
     * Starts untyped and typed workflow stubs in async mode. Sends signal on start.
     *
     * @param object|WorkflowStubInterface $workflow
     * @param string $signal
     * @param array $signalArgs
     * @param array $startArgs
     * @return WorkflowRunInterface
     */
    public function startWithSignal(
        $workflow,
        string $signal,
        array $signalArgs = [],
        array $startArgs = []
    ): WorkflowRunInterface;

    /**
     * Creates workflow client stub that can be used to start a single workflow
     * execution. The first call must be to a method annotated
     * with {@see WorkflowMethod}. After workflow is started it can be also
     * used to send signals or queries to it.
     *
     * Use WorkflowClient->start($workflowStub, ...$args) to start workflow asynchronously.
     *
     * IMPORTANT! Stub is per workflow instance. So new stub should be created
     * for each new one.
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param WorkflowOptions|null $options
     * @return T
     */
    public function newWorkflowStub(
        string $class,
        WorkflowOptions $options = null,
    ): object;

    /**
     * Creates workflow untyped client stub that can be used to start a single
     * workflow execution. After workflow is started it can be also used to send
     * signals or queries to it.
     *
     * Use WorkflowClient->start($workflowStub, ...$args) to start workflow asynchronously.
     *
     * IMPORTANT! Stub is per workflow instance. So new stub should be created
     * for each new one.
     *
     * @param string $workflowType
     * @param WorkflowOptions|null $options
     * @return WorkflowStubInterface
     */
    public function newUntypedWorkflowStub(
        string $workflowType,
        WorkflowOptions $options = null,
    ): WorkflowStubInterface;

    /**
     * Returns workflow stub associated with running workflow.
     *
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param string $workflowID
     * @param string|null $runID
     * @return T
     */
    public function newRunningWorkflowStub(
        string $class,
        string $workflowID,
        ?string $runID = null
    ): object;

    /**
     * Returns untyped workflow stub associated with running workflow.
     *
     * @param string $workflowID
     * @param string|null $runID
     * @param string|null $workflowType
     * @return WorkflowStubInterface
     */
    public function newUntypedRunningWorkflowStub(
        string $workflowID,
        ?string $runID = null,
        ?string $workflowType = null
    ): WorkflowStubInterface;

    /**
     * Creates a new {@link ActivityCompletionClient} that can be used to complete activities
     * asynchronously. Only relevant for activity implementations that called {@link
     * ActivityContext->doNotCompleteOnReturn()}.
     *
     * @return ActivityCompletionClientInterface
     */
    public function newActivityCompletionClient(): ActivityCompletionClientInterface;

    /**
     * Get paginated list of workflow executions using List Filter Query syntax.
     * Query example:
     *
     * <code>
     * WorkflowType='MyWorkflow' and StartTime  between '2022-08-22T15:04:05+00:00' and  '2023-08-22T15:04:05+00:00'
     * </code>
     *
     * @link https://docs.temporal.io/visibility
     * @see self::countWorkflowExecutions()
     *
     * @param non-empty-string $query
     * @param non-empty-string $namespace
     * @param int<0, max> $pageSize Maximum number of workflow info per page.
     *
     * @return Paginator<WorkflowExecutionInfoDto>
     */
    public function listWorkflowExecutions(
        string $query,
        string $namespace = 'default',
        int $pageSize = 10,
    ): Paginator;

    /**
     * Get count of workflow executions using List Filter Query syntax.
     * Query example:
     *
     * <code>
     * WorkflowType='MyWorkflow' and StartTime between '2022-08-22T15:04:05+00:00' and '2023-08-22T15:04:05+00:00'
     * </code>
     *
     * @link https://docs.temporal.io/visibility
     * @see self::listWorkflowExecutions()
     *
     * @param non-empty-string $query
     * @param non-empty-string $namespace
     */
    public function countWorkflowExecutions(
        string $query,
        string $namespace = 'default',
    ): CountWorkflowExecutions;

    /**
     * @param non-empty-string $namespace
     * @param WorkflowExecution $execution
     * @param bool $waitNewEvent If set to true, the RPC call will not resolve until there is a new event which matches,
     *        the $historyEventFilterType, or a timeout is hit. The RPC call will be resolved immediately if the
     *        workflow was already finished.
     * @param int<0, 2>| $historyEventFilterType Filter returned events such that they match the specified filter type.
     *        Available values are {@see HistoryEventFilterType} constants.
     * @param bool $skipArchival
     * @param int<0, max> $pageSize Size of the pages to be requested. It affects internal queries only. Use it if you
     *        want to load limited number of events from the history.
     *
     * @return WorkflowExecutionHistory
     */
    public function getWorkflowHistory(
        WorkflowExecution $execution,
        string $namespace = 'default',
        bool $waitNewEvent = false,
        int $historyEventFilterType = HistoryEventFilterType::HISTORY_EVENT_FILTER_TYPE_ALL_EVENT,
        bool $skipArchival = false,
        int $pageSize = 0,
    ): WorkflowExecutionHistory;
}
