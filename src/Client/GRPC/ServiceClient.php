<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Api\Workflowservice\V1;
use Temporal\Exception\Client\ServiceClientException;

class ServiceClient extends BaseClient
{
    /**
     * RegisterNamespace creates a new namespace which can be used as a container for
     * all resources.
     *
     * A Namespace is a top level entity within Temporal, and is used as a container
     * for resources
     * like workflow executions, task queues, etc. A Namespace acts as a sandbox and
     * provides
     * isolation for all resources within the namespace. All resources belongs to
     * exactly one
     * namespace.
     *
     * @throws ServiceClientException
     */
    public function RegisterNamespace(V1\RegisterNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\RegisterNamespaceResponse
    {
        return $this->invoke("RegisterNamespace", $arg, $ctx);
    }

    /**
     * DescribeNamespace returns the information and configuration for a registered
     * namespace.
     *
     * @throws ServiceClientException
     */
    public function DescribeNamespace(V1\DescribeNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\DescribeNamespaceResponse
    {
        return $this->invoke("DescribeNamespace", $arg, $ctx);
    }

    /**
     * ListNamespaces returns the information and configuration for all namespaces.
     *
     * @throws ServiceClientException
     */
    public function ListNamespaces(V1\ListNamespacesRequest $arg, ?ContextInterface $ctx = null): V1\ListNamespacesResponse
    {
        return $this->invoke("ListNamespaces", $arg, $ctx);
    }

    /**
     * UpdateNamespace is used to update the information and configuration of a
     * registered
     * namespace.
     *
     * @throws ServiceClientException
     */
    public function UpdateNamespace(V1\UpdateNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNamespaceResponse
    {
        return $this->invoke("UpdateNamespace", $arg, $ctx);
    }

    /**
     * DeprecateNamespace is used to update the state of a registered namespace to
     * DEPRECATED.
     *
     * Once the namespace is deprecated it cannot be used to start new workflow
     * executions. Existing
     * workflow executions will continue to run on deprecated namespaces.
     * Deprecated.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: Deprecated --)
     *
     * @throws ServiceClientException
     */
    public function DeprecateNamespace(V1\DeprecateNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\DeprecateNamespaceResponse
    {
        return $this->invoke("DeprecateNamespace", $arg, $ctx);
    }

    /**
     * StartWorkflowExecution starts a new workflow execution.
     *
     * It will create the execution with a `WORKFLOW_EXECUTION_STARTED` event in its
     * history and
     * also schedule the first workflow task. Returns
     * `WorkflowExecutionAlreadyStarted`, if an
     * instance already exists with same workflow id.
     *
     * @throws ServiceClientException
     */
    public function StartWorkflowExecution(V1\StartWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\StartWorkflowExecutionResponse
    {
        return $this->invoke("StartWorkflowExecution", $arg, $ctx);
    }

    /**
     * ExecuteMultiOperation executes multiple operations within a single workflow.
     *
     * Operations are started atomically, meaning if *any* operation fails to be
     * started, none are,
     * and the request fails. Upon start, the API returns only when *all* operations
     * have a response.
     *
     * Upon failure, it returns `MultiOperationExecutionFailure` where the status code
     * equals the status code of the *first* operation that failed to be started.
     *
     * NOTE: Experimental API.
     *
     * @throws ServiceClientException
     */
    public function ExecuteMultiOperation(V1\ExecuteMultiOperationRequest $arg, ?ContextInterface $ctx = null): V1\ExecuteMultiOperationResponse
    {
        return $this->invoke("ExecuteMultiOperation", $arg, $ctx);
    }

    /**
     * GetWorkflowExecutionHistory returns the history of specified workflow execution.
     * Fails with
     * `NotFound` if the specified workflow execution is unknown to the service.
     *
     * @throws ServiceClientException
     */
    public function GetWorkflowExecutionHistory(V1\GetWorkflowExecutionHistoryRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkflowExecutionHistoryResponse
    {
        return $this->invoke("GetWorkflowExecutionHistory", $arg, $ctx);
    }

    /**
     * GetWorkflowExecutionHistoryReverse returns the history of specified workflow
     * execution in reverse
     * order (starting from last event). Fails with`NotFound` if the specified workflow
     * execution is
     * unknown to the service.
     *
     * @throws ServiceClientException
     */
    public function GetWorkflowExecutionHistoryReverse(V1\GetWorkflowExecutionHistoryReverseRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkflowExecutionHistoryReverseResponse
    {
        return $this->invoke("GetWorkflowExecutionHistoryReverse", $arg, $ctx);
    }

    /**
     * PollWorkflowTaskQueue is called by workers to make progress on workflows.
     *
     * A WorkflowTask is dispatched to callers for active workflow executions with
     * pending workflow
     * tasks. The worker is expected to call `RespondWorkflowTaskCompleted` when it is
     * done
     * processing the task. The service will create a `WorkflowTaskStarted` event in
     * the history for
     * this task before handing it to the worker.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function PollWorkflowTaskQueue(V1\PollWorkflowTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\PollWorkflowTaskQueueResponse
    {
        return $this->invoke("PollWorkflowTaskQueue", $arg, $ctx);
    }

    /**
     * RespondWorkflowTaskCompleted is called by workers to successfully complete
     * workflow tasks
     * they received from `PollWorkflowTaskQueue`.
     *
     * Completing a WorkflowTask will write a `WORKFLOW_TASK_COMPLETED` event to the
     * workflow's
     * history, along with events corresponding to whatever commands the SDK generated
     * while
     * executing the task (ex timer started, activity task scheduled, etc).
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function RespondWorkflowTaskCompleted(V1\RespondWorkflowTaskCompletedRequest $arg, ?ContextInterface $ctx = null): V1\RespondWorkflowTaskCompletedResponse
    {
        return $this->invoke("RespondWorkflowTaskCompleted", $arg, $ctx);
    }

    /**
     * RespondWorkflowTaskFailed is called by workers to indicate the processing of a
     * workflow task
     * failed.
     *
     * This results in a `WORKFLOW_TASK_FAILED` event written to the history, and a new
     * workflow
     * task will be scheduled. This API can be used to report unhandled failures
     * resulting from
     * applying the workflow task.
     *
     * Temporal will only append first WorkflowTaskFailed event to the history of
     * workflow execution
     * for consecutive failures.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function RespondWorkflowTaskFailed(V1\RespondWorkflowTaskFailedRequest $arg, ?ContextInterface $ctx = null): V1\RespondWorkflowTaskFailedResponse
    {
        return $this->invoke("RespondWorkflowTaskFailed", $arg, $ctx);
    }

    /**
     * PollActivityTaskQueue is called by workers to process activity tasks from a
     * specific task
     * queue.
     *
     * The worker is expected to call one of the `RespondActivityTaskXXX` methods when
     * it is done
     * processing the task.
     *
     * An activity task is dispatched whenever a `SCHEDULE_ACTIVITY_TASK` command is
     * produced during
     * workflow execution. An in memory `ACTIVITY_TASK_STARTED` event is written to
     * mutable state
     * before the task is dispatched to the worker. The started event, and the final
     * event
     * (`ACTIVITY_TASK_COMPLETED` / `ACTIVITY_TASK_FAILED` / `ACTIVITY_TASK_TIMED_OUT`)
     * will both be
     * written permanently to Workflow execution history when Activity is finished.
     * This is done to
     * avoid writing many events in the case of a failure/retry loop.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function PollActivityTaskQueue(V1\PollActivityTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\PollActivityTaskQueueResponse
    {
        return $this->invoke("PollActivityTaskQueue", $arg, $ctx);
    }

    /**
     * RecordActivityTaskHeartbeat is optionally called by workers while they execute
     * activities.
     *
     * If worker fails to heartbeat within the `heartbeat_timeout` interval for the
     * activity task,
     * then it will be marked as timed out and an `ACTIVITY_TASK_TIMED_OUT` event will
     * be written to
     * the workflow history. Calling `RecordActivityTaskHeartbeat` will fail with
     * `NotFound` in
     * such situations, in that event, the SDK should request cancellation of the
     * activity.
     *
     * @throws ServiceClientException
     */
    public function RecordActivityTaskHeartbeat(V1\RecordActivityTaskHeartbeatRequest $arg, ?ContextInterface $ctx = null): V1\RecordActivityTaskHeartbeatResponse
    {
        return $this->invoke("RecordActivityTaskHeartbeat", $arg, $ctx);
    }

    /**
     * See `RecordActivityTaskHeartbeat`. This version allows clients to record
     * heartbeats by
     * namespace/workflow id/activity id instead of task token.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @throws ServiceClientException
     */
    public function RecordActivityTaskHeartbeatById(V1\RecordActivityTaskHeartbeatByIdRequest $arg, ?ContextInterface $ctx = null): V1\RecordActivityTaskHeartbeatByIdResponse
    {
        return $this->invoke("RecordActivityTaskHeartbeatById", $arg, $ctx);
    }

    /**
     * RespondActivityTaskCompleted is called by workers when they successfully
     * complete an activity
     * task.
     *
     * This results in a new `ACTIVITY_TASK_COMPLETED` event being written to the
     * workflow history
     * and a new workflow task created for the workflow. Fails with `NotFound` if the
     * task token is
     * no longer valid due to activity timeout, already being completed, or never
     * having existed.
     *
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCompleted(V1\RespondActivityTaskCompletedRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskCompletedResponse
    {
        return $this->invoke("RespondActivityTaskCompleted", $arg, $ctx);
    }

    /**
     * See `RecordActivityTaskCompleted`. This version allows clients to record
     * completions by
     * namespace/workflow id/activity id instead of task token.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCompletedById(V1\RespondActivityTaskCompletedByIdRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskCompletedByIdResponse
    {
        return $this->invoke("RespondActivityTaskCompletedById", $arg, $ctx);
    }

    /**
     * RespondActivityTaskFailed is called by workers when processing an activity task
     * fails.
     *
     * This results in a new `ACTIVITY_TASK_FAILED` event being written to the workflow
     * history and
     * a new workflow task created for the workflow. Fails with `NotFound` if the task
     * token is no
     * longer valid due to activity timeout, already being completed, or never having
     * existed.
     *
     * @throws ServiceClientException
     */
    public function RespondActivityTaskFailed(V1\RespondActivityTaskFailedRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskFailedResponse
    {
        return $this->invoke("RespondActivityTaskFailed", $arg, $ctx);
    }

    /**
     * See `RecordActivityTaskFailed`. This version allows clients to record failures
     * by
     * namespace/workflow id/activity id instead of task token.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @throws ServiceClientException
     */
    public function RespondActivityTaskFailedById(V1\RespondActivityTaskFailedByIdRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskFailedByIdResponse
    {
        return $this->invoke("RespondActivityTaskFailedById", $arg, $ctx);
    }

    /**
     * RespondActivityTaskFailed is called by workers when processing an activity task
     * fails.
     *
     * This results in a new `ACTIVITY_TASK_CANCELED` event being written to the
     * workflow history
     * and a new workflow task created for the workflow. Fails with `NotFound` if the
     * task token is
     * no longer valid due to activity timeout, already being completed, or never
     * having existed.
     *
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCanceled(V1\RespondActivityTaskCanceledRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskCanceledResponse
    {
        return $this->invoke("RespondActivityTaskCanceled", $arg, $ctx);
    }

    /**
     * See `RecordActivityTaskCanceled`. This version allows clients to record failures
     * by
     * namespace/workflow id/activity id instead of task token.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCanceledById(V1\RespondActivityTaskCanceledByIdRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskCanceledByIdResponse
    {
        return $this->invoke("RespondActivityTaskCanceledById", $arg, $ctx);
    }

    /**
     * RequestCancelWorkflowExecution is called by workers when they want to request
     * cancellation of
     * a workflow execution.
     *
     * This results in a new `WORKFLOW_EXECUTION_CANCEL_REQUESTED` event being written
     * to the
     * workflow history and a new workflow task created for the workflow. It returns
     * success if the requested
     * workflow is already closed. It fails with 'NotFound' if the requested workflow
     * doesn't exist.
     *
     * @throws ServiceClientException
     */
    public function RequestCancelWorkflowExecution(V1\RequestCancelWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\RequestCancelWorkflowExecutionResponse
    {
        return $this->invoke("RequestCancelWorkflowExecution", $arg, $ctx);
    }

    /**
     * SignalWorkflowExecution is used to send a signal to a running workflow
     * execution.
     *
     * This results in a `WORKFLOW_EXECUTION_SIGNALED` event recorded in the history
     * and a workflow
     * task being created for the execution.
     *
     * @throws ServiceClientException
     */
    public function SignalWorkflowExecution(V1\SignalWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\SignalWorkflowExecutionResponse
    {
        return $this->invoke("SignalWorkflowExecution", $arg, $ctx);
    }

    /**
     * SignalWithStartWorkflowExecution is used to ensure a signal is sent to a
     * workflow, even if
     * it isn't yet started.
     *
     * If the workflow is running, a `WORKFLOW_EXECUTION_SIGNALED` event is recorded in
     * the history
     * and a workflow task is generated.
     *
     * If the workflow is not running or not found, then the workflow is created with
     * `WORKFLOW_EXECUTION_STARTED` and `WORKFLOW_EXECUTION_SIGNALED` events in its
     * history, and a
     * workflow task is generated.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "With" is used to indicate combined operation. --)
     *
     * @throws ServiceClientException
     */
    public function SignalWithStartWorkflowExecution(V1\SignalWithStartWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\SignalWithStartWorkflowExecutionResponse
    {
        return $this->invoke("SignalWithStartWorkflowExecution", $arg, $ctx);
    }

    /**
     * ResetWorkflowExecution will reset an existing workflow execution to a specified
     * `WORKFLOW_TASK_COMPLETED` event (exclusive). It will immediately terminate the
     * current
     * execution instance.
     * TODO: Does exclusive here mean *just* the completed event, or also WFT started?
     * Otherwise the task is doomed to time out?
     *
     * @throws ServiceClientException
     */
    public function ResetWorkflowExecution(V1\ResetWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\ResetWorkflowExecutionResponse
    {
        return $this->invoke("ResetWorkflowExecution", $arg, $ctx);
    }

    /**
     * TerminateWorkflowExecution terminates an existing workflow execution by
     * recording a
     * `WORKFLOW_EXECUTION_TERMINATED` event in the history and immediately terminating
     * the
     * execution instance.
     *
     * @throws ServiceClientException
     */
    public function TerminateWorkflowExecution(V1\TerminateWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\TerminateWorkflowExecutionResponse
    {
        return $this->invoke("TerminateWorkflowExecution", $arg, $ctx);
    }

    /**
     * DeleteWorkflowExecution asynchronously deletes a specific Workflow Execution
     * (when
     * WorkflowExecution.run_id is provided) or the latest Workflow Execution (when
     * WorkflowExecution.run_id is not provided). If the Workflow Execution is Running,
     * it will be
     * terminated before deletion.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: Workflow deletion not exposed to HTTP, users should use
     * cancel or terminate. --)
     *
     * @throws ServiceClientException
     */
    public function DeleteWorkflowExecution(V1\DeleteWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\DeleteWorkflowExecutionResponse
    {
        return $this->invoke("DeleteWorkflowExecution", $arg, $ctx);
    }

    /**
     * ListOpenWorkflowExecutions is a visibility API to list the open executions in a
     * specific namespace.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @throws ServiceClientException
     */
    public function ListOpenWorkflowExecutions(V1\ListOpenWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ListOpenWorkflowExecutionsResponse
    {
        return $this->invoke("ListOpenWorkflowExecutions", $arg, $ctx);
    }

    /**
     * ListClosedWorkflowExecutions is a visibility API to list the closed executions
     * in a specific namespace.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @throws ServiceClientException
     */
    public function ListClosedWorkflowExecutions(V1\ListClosedWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ListClosedWorkflowExecutionsResponse
    {
        return $this->invoke("ListClosedWorkflowExecutions", $arg, $ctx);
    }

    /**
     * ListWorkflowExecutions is a visibility API to list workflow executions in a
     * specific namespace.
     *
     * @throws ServiceClientException
     */
    public function ListWorkflowExecutions(V1\ListWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ListWorkflowExecutionsResponse
    {
        return $this->invoke("ListWorkflowExecutions", $arg, $ctx);
    }

    /**
     * ListArchivedWorkflowExecutions is a visibility API to list archived workflow
     * executions in a specific namespace.
     *
     * @throws ServiceClientException
     */
    public function ListArchivedWorkflowExecutions(V1\ListArchivedWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ListArchivedWorkflowExecutionsResponse
    {
        return $this->invoke("ListArchivedWorkflowExecutions", $arg, $ctx);
    }

    /**
     * ScanWorkflowExecutions is a visibility API to list large amount of workflow
     * executions in a specific namespace without order.
     *
     * Deprecated: Replaced with `ListWorkflowExecutions`.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @throws ServiceClientException
     */
    public function ScanWorkflowExecutions(V1\ScanWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ScanWorkflowExecutionsResponse
    {
        return $this->invoke("ScanWorkflowExecutions", $arg, $ctx);
    }

    /**
     * CountWorkflowExecutions is a visibility API to count of workflow executions in a
     * specific namespace.
     *
     * @throws ServiceClientException
     */
    public function CountWorkflowExecutions(V1\CountWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\CountWorkflowExecutionsResponse
    {
        return $this->invoke("CountWorkflowExecutions", $arg, $ctx);
    }

    /**
     * GetSearchAttributes is a visibility API to get all legal keys that could be used
     * in list APIs
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose this search attribute API to HTTP (but
     * may expose on OperatorService). --)
     *
     * @throws ServiceClientException
     */
    public function GetSearchAttributes(V1\GetSearchAttributesRequest $arg, ?ContextInterface $ctx = null): V1\GetSearchAttributesResponse
    {
        return $this->invoke("GetSearchAttributes", $arg, $ctx);
    }

    /**
     * RespondQueryTaskCompleted is called by workers to complete queries which were
     * delivered on
     * the `query` (not `queries`) field of a `PollWorkflowTaskQueueResponse`.
     *
     * Completing the query will unblock the corresponding client call to
     * `QueryWorkflow` and return
     * the query result a response.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function RespondQueryTaskCompleted(V1\RespondQueryTaskCompletedRequest $arg, ?ContextInterface $ctx = null): V1\RespondQueryTaskCompletedResponse
    {
        return $this->invoke("RespondQueryTaskCompleted", $arg, $ctx);
    }

    /**
     * ResetStickyTaskQueue resets the sticky task queue related information in the
     * mutable state of
     * a given workflow. This is prudent for workers to perform if a workflow has been
     * paged out of
     * their cache.
     *
     * Things cleared are:
     * 1. StickyTaskQueue
     * 2. StickyScheduleToStartTimeout
     *
     * When possible, ShutdownWorker should be preferred over
     * ResetStickyTaskQueue (particularly when a worker is shutting down or
     * cycling).
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function ResetStickyTaskQueue(V1\ResetStickyTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\ResetStickyTaskQueueResponse
    {
        return $this->invoke("ResetStickyTaskQueue", $arg, $ctx);
    }

    /**
     * ShutdownWorker is used to indicate that the given sticky task
     * queue is no longer being polled by its worker. Following the completion of
     * ShutdownWorker, newly-added workflow tasks will instead be placed
     * in the normal task queue, eligible for any worker to pick up.
     *
     * ShutdownWorker should be called by workers while shutting down,
     * after they've shut down their pollers. If another sticky poll
     * request is issued, the sticky task queue will be revived.
     *
     * As of Temporal Server v1.25.0, ShutdownWorker hasn't yet been implemented.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function ShutdownWorker(V1\ShutdownWorkerRequest $arg, ?ContextInterface $ctx = null): V1\ShutdownWorkerResponse
    {
        return $this->invoke("ShutdownWorker", $arg, $ctx);
    }

    /**
     * QueryWorkflow requests a query be executed for a specified workflow execution.
     *
     * @throws ServiceClientException
     */
    public function QueryWorkflow(V1\QueryWorkflowRequest $arg, ?ContextInterface $ctx = null): V1\QueryWorkflowResponse
    {
        return $this->invoke("QueryWorkflow", $arg, $ctx);
    }

    /**
     * DescribeWorkflowExecution returns information about the specified workflow
     * execution.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorkflowExecution(V1\DescribeWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\DescribeWorkflowExecutionResponse
    {
        return $this->invoke("DescribeWorkflowExecution", $arg, $ctx);
    }

    /**
     * DescribeTaskQueue returns the following information about the target task queue,
     * broken down by Build ID:
     * - List of pollers
     * - Workflow Reachability status
     * - Backlog info for Workflow and/or Activity tasks
     *
     * @throws ServiceClientException
     */
    public function DescribeTaskQueue(V1\DescribeTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\DescribeTaskQueueResponse
    {
        return $this->invoke("DescribeTaskQueue", $arg, $ctx);
    }

    /**
     * GetClusterInfo returns information about temporal cluster
     *
     * @throws ServiceClientException
     */
    public function GetClusterInfo(V1\GetClusterInfoRequest $arg, ?ContextInterface $ctx = null): V1\GetClusterInfoResponse
    {
        return $this->invoke("GetClusterInfo", $arg, $ctx);
    }

    /**
     * GetSystemInfo returns information about the system.
     *
     * @throws ServiceClientException
     */
    public function GetSystemInfo(V1\GetSystemInfoRequest $arg, ?ContextInterface $ctx = null): V1\GetSystemInfoResponse
    {
        return $this->invoke("GetSystemInfo", $arg, $ctx);
    }

    /**
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose this low-level API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function ListTaskQueuePartitions(V1\ListTaskQueuePartitionsRequest $arg, ?ContextInterface $ctx = null): V1\ListTaskQueuePartitionsResponse
    {
        return $this->invoke("ListTaskQueuePartitions", $arg, $ctx);
    }

    /**
     * Creates a new schedule.
     *
     * @throws ServiceClientException
     */
    public function CreateSchedule(V1\CreateScheduleRequest $arg, ?ContextInterface $ctx = null): V1\CreateScheduleResponse
    {
        return $this->invoke("CreateSchedule", $arg, $ctx);
    }

    /**
     * Returns the schedule description and current state of an existing schedule.
     *
     * @throws ServiceClientException
     */
    public function DescribeSchedule(V1\DescribeScheduleRequest $arg, ?ContextInterface $ctx = null): V1\DescribeScheduleResponse
    {
        return $this->invoke("DescribeSchedule", $arg, $ctx);
    }

    /**
     * Changes the configuration or state of an existing schedule.
     *
     * @throws ServiceClientException
     */
    public function UpdateSchedule(V1\UpdateScheduleRequest $arg, ?ContextInterface $ctx = null): V1\UpdateScheduleResponse
    {
        return $this->invoke("UpdateSchedule", $arg, $ctx);
    }

    /**
     * Makes a specific change to a schedule or triggers an immediate action.
     *
     * @throws ServiceClientException
     */
    public function PatchSchedule(V1\PatchScheduleRequest $arg, ?ContextInterface $ctx = null): V1\PatchScheduleResponse
    {
        return $this->invoke("PatchSchedule", $arg, $ctx);
    }

    /**
     * Lists matching times within a range.
     *
     * @throws ServiceClientException
     */
    public function ListScheduleMatchingTimes(V1\ListScheduleMatchingTimesRequest $arg, ?ContextInterface $ctx = null): V1\ListScheduleMatchingTimesResponse
    {
        return $this->invoke("ListScheduleMatchingTimes", $arg, $ctx);
    }

    /**
     * Deletes a schedule, removing it from the system.
     *
     * @throws ServiceClientException
     */
    public function DeleteSchedule(V1\DeleteScheduleRequest $arg, ?ContextInterface $ctx = null): V1\DeleteScheduleResponse
    {
        return $this->invoke("DeleteSchedule", $arg, $ctx);
    }

    /**
     * List all schedules in a namespace.
     *
     * @throws ServiceClientException
     */
    public function ListSchedules(V1\ListSchedulesRequest $arg, ?ContextInterface $ctx = null): V1\ListSchedulesResponse
    {
        return $this->invoke("ListSchedules", $arg, $ctx);
    }

    /**
     * Deprecated. Use `UpdateWorkerVersioningRules`.
     *
     * Allows users to specify sets of worker build id versions on a per task queue
     * basis. Versions
     * are ordered, and may be either compatible with some extant version, or a new
     * incompatible
     * version, forming sets of ids which are incompatible with each other, but whose
     * contained
     * members are compatible with one another.
     *
     * A single build id may be mapped to multiple task queues using this API for cases
     * where a single process hosts
     * multiple workers.
     *
     * To query which workers can be retired, use the `GetWorkerTaskReachability` API.
     *
     * NOTE: The number of task queues mapped to a single build id is limited by the
     * `limit.taskQueuesPerBuildId`
     * (default is 20), if this limit is exceeded this API will error with a
     * FailedPrecondition.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do yet expose versioning API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkerBuildIdCompatibility(V1\UpdateWorkerBuildIdCompatibilityRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkerBuildIdCompatibilityResponse
    {
        return $this->invoke("UpdateWorkerBuildIdCompatibility", $arg, $ctx);
    }

    /**
     * Deprecated. Use `GetWorkerVersioningRules`.
     * Fetches the worker build id versioning sets for a task queue.
     *
     * @throws ServiceClientException
     */
    public function GetWorkerBuildIdCompatibility(V1\GetWorkerBuildIdCompatibilityRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkerBuildIdCompatibilityResponse
    {
        return $this->invoke("GetWorkerBuildIdCompatibility", $arg, $ctx);
    }

    /**
     * Use this API to manage Worker Versioning Rules for a given Task Queue. There are
     * two types of
     * rules: Build ID Assignment rules and Compatible Build ID Redirect rules.
     *
     * Assignment rules determine how to assign new executions to a Build IDs. Their
     * primary
     * use case is to specify the latest Build ID but they have powerful features for
     * gradual rollout
     * of a new Build ID.
     *
     * Once a workflow execution is assigned to a Build ID and it completes its first
     * Workflow Task,
     * the workflow stays on the assigned Build ID regardless of changes in assignment
     * rules. This
     * eliminates the need for compatibility between versions when you only care about
     * using the new
     * version for new workflows and let existing workflows finish in their own
     * version.
     *
     * Activities, Child Workflows and Continue-as-New executions have the option to
     * inherit the
     * Build ID of their parent/previous workflow or use the latest assignment rules to
     * independently
     * select a Build ID.
     *
     * Redirect rules should only be used when you want to move workflows and
     * activities assigned to
     * one Build ID (source) to another compatible Build ID (target). You are
     * responsible to make sure
     * the target Build ID of a redirect rule is able to process event histories made
     * by the source
     * Build ID by using [Patching](https://docs.temporal.io/workflows#patching) or
     * other means.
     *
     * WARNING: Worker Versioning is not yet stable and the API and behavior may change
     * incompatibly.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do yet expose versioning API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkerVersioningRules(V1\UpdateWorkerVersioningRulesRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkerVersioningRulesResponse
    {
        return $this->invoke("UpdateWorkerVersioningRules", $arg, $ctx);
    }

    /**
     * Fetches the Build ID assignment and redirect rules for a Task Queue.
     * WARNING: Worker Versioning is not yet stable and the API and behavior may change
     * incompatibly.
     *
     * @throws ServiceClientException
     */
    public function GetWorkerVersioningRules(V1\GetWorkerVersioningRulesRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkerVersioningRulesResponse
    {
        return $this->invoke("GetWorkerVersioningRules", $arg, $ctx);
    }

    /**
     * Deprecated. Use `DescribeTaskQueue`.
     *
     * Fetches task reachability to determine whether a worker may be retired.
     * The request may specify task queues to query for or let the server fetch all
     * task queues mapped to the given
     * build IDs.
     *
     * When requesting a large number of task queues or all task queues associated with
     * the given build ids in a
     * namespace, all task queues will be listed in the response but some of them may
     * not contain reachability
     * information due to a server enforced limit. When reaching the limit, task queues
     * that reachability information
     * could not be retrieved for will be marked with a single
     * TASK_REACHABILITY_UNSPECIFIED entry. The caller may issue
     * another call to get the reachability for those task queues.
     *
     * Open source users can adjust this limit by setting the server's dynamic config
     * value for
     * `limit.reachabilityTaskQueueScan` with the caveat that this call can strain the
     * visibility store.
     *
     * @throws ServiceClientException
     */
    public function GetWorkerTaskReachability(V1\GetWorkerTaskReachabilityRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkerTaskReachabilityResponse
    {
        return $this->invoke("GetWorkerTaskReachability", $arg, $ctx);
    }

    /**
     * Describes a worker deployment.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     * Deprecated. Replaced with `DescribeWorkerDeploymentVersion`.
     *
     * @throws ServiceClientException
     */
    public function DescribeDeployment(V1\DescribeDeploymentRequest $arg, ?ContextInterface $ctx = null): V1\DescribeDeploymentResponse
    {
        return $this->invoke("DescribeDeployment", $arg, $ctx);
    }

    /**
     * Describes a worker deployment version.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorkerDeploymentVersion(V1\DescribeWorkerDeploymentVersionRequest $arg, ?ContextInterface $ctx = null): V1\DescribeWorkerDeploymentVersionResponse
    {
        return $this->invoke("DescribeWorkerDeploymentVersion", $arg, $ctx);
    }

    /**
     * Lists worker deployments in the namespace. Optionally can filter based on
     * deployment series
     * name.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     * Deprecated. Replaced with `ListWorkerDeployments`.
     *
     * @throws ServiceClientException
     */
    public function ListDeployments(V1\ListDeploymentsRequest $arg, ?ContextInterface $ctx = null): V1\ListDeploymentsResponse
    {
        return $this->invoke("ListDeployments", $arg, $ctx);
    }

    /**
     * Returns the reachability level of a worker deployment to help users decide when
     * it is time
     * to decommission a deployment. Reachability level is calculated based on the
     * deployment's
     * `status` and existing workflows that depend on the given deployment for their
     * execution.
     * Calculating reachability is relatively expensive. Therefore, server might return
     * a recently
     * cached value. In such a case, the `last_update_time` will inform you about the
     * actual
     * reachability calculation time.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     * Deprecated. Replaced with `DrainageInfo` returned by
     * `DescribeWorkerDeploymentVersion`.
     *
     * @throws ServiceClientException
     */
    public function GetDeploymentReachability(V1\GetDeploymentReachabilityRequest $arg, ?ContextInterface $ctx = null): V1\GetDeploymentReachabilityResponse
    {
        return $this->invoke("GetDeploymentReachability", $arg, $ctx);
    }

    /**
     * Returns the current deployment (and its info) for a given deployment series.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     * Deprecated. Replaced by `current_version` returned by
     * `DescribeWorkerDeployment`.
     *
     * @throws ServiceClientException
     */
    public function GetCurrentDeployment(V1\GetCurrentDeploymentRequest $arg, ?ContextInterface $ctx = null): V1\GetCurrentDeploymentResponse
    {
        return $this->invoke("GetCurrentDeployment", $arg, $ctx);
    }

    /**
     * Sets a deployment as the current deployment for its deployment series. Can
     * optionally update
     * the metadata of the deployment as well.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     * Deprecated. Replaced by `SetWorkerDeploymentCurrentVersion`.
     *
     * @throws ServiceClientException
     */
    public function SetCurrentDeployment(V1\SetCurrentDeploymentRequest $arg, ?ContextInterface $ctx = null): V1\SetCurrentDeploymentResponse
    {
        return $this->invoke("SetCurrentDeployment", $arg, $ctx);
    }

    /**
     * Set/unset the Current Version of a Worker Deployment. Automatically unsets the
     * Ramping
     * Version if it is the Version being set as Current.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @throws ServiceClientException
     */
    public function SetWorkerDeploymentCurrentVersion(V1\SetWorkerDeploymentCurrentVersionRequest $arg, ?ContextInterface $ctx = null): V1\SetWorkerDeploymentCurrentVersionResponse
    {
        return $this->invoke("SetWorkerDeploymentCurrentVersion", $arg, $ctx);
    }

    /**
     * Describes a Worker Deployment.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorkerDeployment(V1\DescribeWorkerDeploymentRequest $arg, ?ContextInterface $ctx = null): V1\DescribeWorkerDeploymentResponse
    {
        return $this->invoke("DescribeWorkerDeployment", $arg, $ctx);
    }

    /**
     * Deletes records of (an old) Deployment. A deployment can only be deleted if
     * it has no Version in it.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @throws ServiceClientException
     */
    public function DeleteWorkerDeployment(V1\DeleteWorkerDeploymentRequest $arg, ?ContextInterface $ctx = null): V1\DeleteWorkerDeploymentResponse
    {
        return $this->invoke("DeleteWorkerDeployment", $arg, $ctx);
    }

    /**
     * Used for manual deletion of Versions. User can delete a Version only when all
     * the
     * following conditions are met:
     * - It is not the Current or Ramping Version of its Deployment.
     * - It has no active pollers (none of the task queues in the Version have pollers)
     * - It is not draining (see WorkerDeploymentVersionInfo.drainage_info). This
     * condition
     * can be skipped by passing `skip-drainage=true`.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @throws ServiceClientException
     */
    public function DeleteWorkerDeploymentVersion(V1\DeleteWorkerDeploymentVersionRequest $arg, ?ContextInterface $ctx = null): V1\DeleteWorkerDeploymentVersionResponse
    {
        return $this->invoke("DeleteWorkerDeploymentVersion", $arg, $ctx);
    }

    /**
     * Set/unset the Ramping Version of a Worker Deployment and its ramp percentage.
     * Can be used for
     * gradual ramp to unversioned workers too.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @throws ServiceClientException
     */
    public function SetWorkerDeploymentRampingVersion(V1\SetWorkerDeploymentRampingVersionRequest $arg, ?ContextInterface $ctx = null): V1\SetWorkerDeploymentRampingVersionResponse
    {
        return $this->invoke("SetWorkerDeploymentRampingVersion", $arg, $ctx);
    }

    /**
     * Lists all Worker Deployments that are tracked in the Namespace.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @throws ServiceClientException
     */
    public function ListWorkerDeployments(V1\ListWorkerDeploymentsRequest $arg, ?ContextInterface $ctx = null): V1\ListWorkerDeploymentsResponse
    {
        return $this->invoke("ListWorkerDeployments", $arg, $ctx);
    }

    /**
     * Updates the user-given metadata attached to a Worker Deployment Version.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkerDeploymentVersionMetadata(V1\UpdateWorkerDeploymentVersionMetadataRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkerDeploymentVersionMetadataResponse
    {
        return $this->invoke("UpdateWorkerDeploymentVersionMetadata", $arg, $ctx);
    }

    /**
     * Invokes the specified Update function on user Workflow code.
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkflowExecution(V1\UpdateWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkflowExecutionResponse
    {
        return $this->invoke("UpdateWorkflowExecution", $arg, $ctx);
    }

    /**
     * Polls a Workflow Execution for the outcome of a Workflow Update
     * previously issued through the UpdateWorkflowExecution RPC. The effective
     * timeout on this call will be shorter of the the caller-supplied gRPC
     * timeout and the server's configured long-poll timeout.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We don't expose update polling API to HTTP in favor of a
     * potential future non-blocking form. --)
     *
     * @throws ServiceClientException
     */
    public function PollWorkflowExecutionUpdate(V1\PollWorkflowExecutionUpdateRequest $arg, ?ContextInterface $ctx = null): V1\PollWorkflowExecutionUpdateResponse
    {
        return $this->invoke("PollWorkflowExecutionUpdate", $arg, $ctx);
    }

    /**
     * StartBatchOperation starts a new batch operation
     *
     * @throws ServiceClientException
     */
    public function StartBatchOperation(V1\StartBatchOperationRequest $arg, ?ContextInterface $ctx = null): V1\StartBatchOperationResponse
    {
        return $this->invoke("StartBatchOperation", $arg, $ctx);
    }

    /**
     * StopBatchOperation stops a batch operation
     *
     * @throws ServiceClientException
     */
    public function StopBatchOperation(V1\StopBatchOperationRequest $arg, ?ContextInterface $ctx = null): V1\StopBatchOperationResponse
    {
        return $this->invoke("StopBatchOperation", $arg, $ctx);
    }

    /**
     * DescribeBatchOperation returns the information about a batch operation
     *
     * @throws ServiceClientException
     */
    public function DescribeBatchOperation(V1\DescribeBatchOperationRequest $arg, ?ContextInterface $ctx = null): V1\DescribeBatchOperationResponse
    {
        return $this->invoke("DescribeBatchOperation", $arg, $ctx);
    }

    /**
     * ListBatchOperations returns a list of batch operations
     *
     * @throws ServiceClientException
     */
    public function ListBatchOperations(V1\ListBatchOperationsRequest $arg, ?ContextInterface $ctx = null): V1\ListBatchOperationsResponse
    {
        return $this->invoke("ListBatchOperations", $arg, $ctx);
    }

    /**
     * PollNexusTaskQueue is a long poll call used by workers to receive Nexus tasks.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function PollNexusTaskQueue(V1\PollNexusTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\PollNexusTaskQueueResponse
    {
        return $this->invoke("PollNexusTaskQueue", $arg, $ctx);
    }

    /**
     * RespondNexusTaskCompleted is called by workers to respond to Nexus tasks
     * received via PollNexusTaskQueue.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function RespondNexusTaskCompleted(V1\RespondNexusTaskCompletedRequest $arg, ?ContextInterface $ctx = null): V1\RespondNexusTaskCompletedResponse
    {
        return $this->invoke("RespondNexusTaskCompleted", $arg, $ctx);
    }

    /**
     * RespondNexusTaskFailed is called by workers to fail Nexus tasks received via
     * PollNexusTaskQueue.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function RespondNexusTaskFailed(V1\RespondNexusTaskFailedRequest $arg, ?ContextInterface $ctx = null): V1\RespondNexusTaskFailedResponse
    {
        return $this->invoke("RespondNexusTaskFailed", $arg, $ctx);
    }

    /**
     * UpdateActivityOptions is called by the client to update the options of an
     * activity by its ID or type.
     * If there are multiple pending activities of the provided type - all of them will
     * be updated.
     *
     * @throws ServiceClientException
     */
    public function UpdateActivityOptions(V1\UpdateActivityOptionsRequest $arg, ?ContextInterface $ctx = null): V1\UpdateActivityOptionsResponse
    {
        return $this->invoke("UpdateActivityOptions", $arg, $ctx);
    }

    /**
     * UpdateWorkflowExecutionOptions partially updates the WorkflowExecutionOptions of
     * an existing workflow execution.
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkflowExecutionOptions(V1\UpdateWorkflowExecutionOptionsRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkflowExecutionOptionsResponse
    {
        return $this->invoke("UpdateWorkflowExecutionOptions", $arg, $ctx);
    }

    /**
     * PauseActivity pauses the execution of an activity specified by its ID or type.
     * If there are multiple pending activities of the provided type - all of them will
     * be paused
     *
     * Pausing an activity means:
     * - If the activity is currently waiting for a retry or is running and
     * subsequently fails,
     * it will not be rescheduled until it is unpaused.
     * - If the activity is already paused, calling this method will have no effect.
     * - If the activity is running and finishes successfully, the activity will be
     * completed.
     * - If the activity is running and finishes with failure:
     * if there is no retry left - the activity will be completed.
     * if there are more retries left - the activity will be paused.
     * For long-running activities:
     * - activities in paused state will send a cancellation with "activity_paused" set
     * to 'true' in response to 'RecordActivityTaskHeartbeat'.
     * - The activity should respond to the cancellation accordingly.
     *
     * Returns a `NotFound` error if there is no pending activity with the provided ID
     * or type
     *
     * @throws ServiceClientException
     */
    public function PauseActivity(V1\PauseActivityRequest $arg, ?ContextInterface $ctx = null): V1\PauseActivityResponse
    {
        return $this->invoke("PauseActivity", $arg, $ctx);
    }

    /**
     * UnpauseActivity unpauses the execution of an activity specified by its ID or
     * type.
     * If there are multiple pending activities of the provided type - all of them will
     * be unpaused.
     *
     * If activity is not paused, this call will have no effect.
     * If the activity was paused while waiting for retry, it will be scheduled
     * immediately (* see 'jitter' flag).
     * Once the activity is unpaused, all timeout timers will be regenerated.
     *
     * Flags:
     * 'jitter': the activity will be scheduled at a random time within the jitter
     * duration.
     * 'reset_attempts': the number of attempts will be reset.
     * 'reset_heartbeat': the activity heartbeat timer and heartbeats will be reset.
     *
     * Returns a `NotFound` error if there is no pending activity with the provided ID
     * or type
     *
     * @throws ServiceClientException
     */
    public function UnpauseActivity(V1\UnpauseActivityRequest $arg, ?ContextInterface $ctx = null): V1\UnpauseActivityResponse
    {
        return $this->invoke("UnpauseActivity", $arg, $ctx);
    }

    /**
     * ResetActivity resets the execution of an activity specified by its ID or type.
     * If there are multiple pending activities of the provided type - all of them will
     * be reset.
     *
     * Resetting an activity means:
     * number of attempts will be reset to 0.
     * activity timeouts will be reset.
     * if the activity is waiting for retry, and it is not paused or 'keep_paused' is
     * not provided:
     * it will be scheduled immediately (* see 'jitter' flag),
     *
     * Flags:
     *
     * 'jitter': the activity will be scheduled at a random time within the jitter
     * duration.
     * If the activity currently paused it will be unpaused, unless 'keep_paused' flag
     * is provided.
     * 'reset_heartbeats': the activity heartbeat timer and heartbeats will be reset.
     * 'keep_paused': if the activity is paused, it will remain paused.
     *
     * Returns a `NotFound` error if there is no pending activity with the provided ID
     * or type.
     *
     * @throws ServiceClientException
     */
    public function ResetActivity(V1\ResetActivityRequest $arg, ?ContextInterface $ctx = null): V1\ResetActivityResponse
    {
        return $this->invoke("ResetActivity", $arg, $ctx);
    }

    /**
     * Create a new workflow rule. The rules are used to control the workflow
     * execution.
     * The rule will be applied to all running and new workflows in the namespace.
     * If the rule with such ID already exist this call will fail
     * Note: the rules are part of namespace configuration and will be stored in the
     * namespace config.
     * Namespace config is eventually consistent.
     *
     * @throws ServiceClientException
     */
    public function CreateWorkflowRule(V1\CreateWorkflowRuleRequest $arg, ?ContextInterface $ctx = null): V1\CreateWorkflowRuleResponse
    {
        return $this->invoke("CreateWorkflowRule", $arg, $ctx);
    }

    /**
     * DescribeWorkflowRule return the rule specification for existing rule id.
     * If there is no rule with such id - NOT FOUND error will be returned.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorkflowRule(V1\DescribeWorkflowRuleRequest $arg, ?ContextInterface $ctx = null): V1\DescribeWorkflowRuleResponse
    {
        return $this->invoke("DescribeWorkflowRule", $arg, $ctx);
    }

    /**
     * Delete rule by rule id
     *
     * @throws ServiceClientException
     */
    public function DeleteWorkflowRule(V1\DeleteWorkflowRuleRequest $arg, ?ContextInterface $ctx = null): V1\DeleteWorkflowRuleResponse
    {
        return $this->invoke("DeleteWorkflowRule", $arg, $ctx);
    }

    /**
     * Return all namespace workflow rules
     *
     * @throws ServiceClientException
     */
    public function ListWorkflowRules(V1\ListWorkflowRulesRequest $arg, ?ContextInterface $ctx = null): V1\ListWorkflowRulesResponse
    {
        return $this->invoke("ListWorkflowRules", $arg, $ctx);
    }

    /**
     * TriggerWorkflowRule allows to:
     * trigger existing rule for a specific workflow execution;
     * trigger rule for a specific workflow execution without creating a rule;
     * This is useful for one-off operations.
     *
     * @throws ServiceClientException
     */
    public function TriggerWorkflowRule(V1\TriggerWorkflowRuleRequest $arg, ?ContextInterface $ctx = null): V1\TriggerWorkflowRuleResponse
    {
        return $this->invoke("TriggerWorkflowRule", $arg, $ctx);
    }
}
