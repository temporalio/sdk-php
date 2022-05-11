<?php
/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Temporal\Client\GRPC;

use Temporal\Api\Workflowservice\V1;
use Temporal\Exception\Client\ServiceClientException;

/**
 * @codeCoverageIgnore
 */
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
     * @param V1\RegisterNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RegisterNamespaceResponse
     * @throws ServiceClientException
     */
    public function RegisterNamespace(V1\RegisterNamespaceRequest $arg, ContextInterface $ctx = null) : V1\RegisterNamespaceResponse
    {
        return $this->invoke("RegisterNamespace", $arg, $ctx);
    }

    /**
     * DescribeNamespace returns the information and configuration for a registered
     * namespace.
     *
     * @param V1\DescribeNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeNamespaceResponse
     * @throws ServiceClientException
     */
    public function DescribeNamespace(V1\DescribeNamespaceRequest $arg, ContextInterface $ctx = null) : V1\DescribeNamespaceResponse
    {
        return $this->invoke("DescribeNamespace", $arg, $ctx);
    }

    /**
     * ListNamespaces returns the information and configuration for all namespaces.
     *
     * @param V1\ListNamespacesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListNamespacesResponse
     * @throws ServiceClientException
     */
    public function ListNamespaces(V1\ListNamespacesRequest $arg, ContextInterface $ctx = null) : V1\ListNamespacesResponse
    {
        return $this->invoke("ListNamespaces", $arg, $ctx);
    }

    /**
     * UpdateNamespace is used to update the information and configuration of a
     * registered
     * namespace.
     *
     * (-- api-linter: core::0134::method-signature=disabled
     * aip.dev/not-precedent: UpdateNamespace RPC doesn't follow Google API format. --)
     * (-- api-linter: core::0134::response-message-name=disabled
     * aip.dev/not-precedent: UpdateNamespace RPC doesn't follow Google API format. --)
     *
     * @param V1\UpdateNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateNamespaceResponse
     * @throws ServiceClientException
     */
    public function UpdateNamespace(V1\UpdateNamespaceRequest $arg, ContextInterface $ctx = null) : V1\UpdateNamespaceResponse
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
     * @param V1\DeprecateNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DeprecateNamespaceResponse
     * @throws ServiceClientException
     */
    public function DeprecateNamespace(V1\DeprecateNamespaceRequest $arg, ContextInterface $ctx = null) : V1\DeprecateNamespaceResponse
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
     * @param V1\StartWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\StartWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function StartWorkflowExecution(V1\StartWorkflowExecutionRequest $arg, ContextInterface $ctx = null) : V1\StartWorkflowExecutionResponse
    {
        return $this->invoke("StartWorkflowExecution", $arg, $ctx);
    }

    /**
     * GetWorkflowExecutionHistory returns the history of specified workflow execution.
     * Fails with
     * `NotFound` if the specified workflow execution is unknown to the service.
     *
     * @param V1\GetWorkflowExecutionHistoryRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetWorkflowExecutionHistoryResponse
     * @throws ServiceClientException
     */
    public function GetWorkflowExecutionHistory(V1\GetWorkflowExecutionHistoryRequest $arg, ContextInterface $ctx = null) : V1\GetWorkflowExecutionHistoryResponse
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
     * @param
     * V1\GetWorkflowExecutionHistoryReverseRequest $arg
     * @param ContextInterface|null $ctx
     * @return
     * V1\GetWorkflowExecutionHistoryReverseResponse
     * @throws ServiceClientException
     */
    public function GetWorkflowExecutionHistoryReverse(V1\GetWorkflowExecutionHistoryReverseRequest $arg, ContextInterface $ctx = null) : V1\GetWorkflowExecutionHistoryReverseResponse
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
     * @param V1\PollWorkflowTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PollWorkflowTaskQueueResponse
     * @throws ServiceClientException
     */
    public function PollWorkflowTaskQueue(V1\PollWorkflowTaskQueueRequest $arg, ContextInterface $ctx = null) : V1\PollWorkflowTaskQueueResponse
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
     * @param V1\RespondWorkflowTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondWorkflowTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondWorkflowTaskCompleted(V1\RespondWorkflowTaskCompletedRequest $arg, ContextInterface $ctx = null) : V1\RespondWorkflowTaskCompletedResponse
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
     * @param V1\RespondWorkflowTaskFailedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondWorkflowTaskFailedResponse
     * @throws ServiceClientException
     */
    public function RespondWorkflowTaskFailed(V1\RespondWorkflowTaskFailedRequest $arg, ContextInterface $ctx = null) : V1\RespondWorkflowTaskFailedResponse
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
     * @param V1\PollActivityTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PollActivityTaskQueueResponse
     * @throws ServiceClientException
     */
    public function PollActivityTaskQueue(V1\PollActivityTaskQueueRequest $arg, ContextInterface $ctx = null) : V1\PollActivityTaskQueueResponse
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
     * @param V1\RecordActivityTaskHeartbeatRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RecordActivityTaskHeartbeatResponse
     * @throws ServiceClientException
     */
    public function RecordActivityTaskHeartbeat(V1\RecordActivityTaskHeartbeatRequest $arg, ContextInterface $ctx = null) : V1\RecordActivityTaskHeartbeatResponse
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
     * @param V1\RecordActivityTaskHeartbeatByIdRequest
     * $arg
     * @param ContextInterface|null $ctx
     * @return V1\RecordActivityTaskHeartbeatByIdResponse
     * @throws ServiceClientException
     */
    public function RecordActivityTaskHeartbeatById(V1\RecordActivityTaskHeartbeatByIdRequest $arg, ContextInterface $ctx = null) : V1\RecordActivityTaskHeartbeatByIdResponse
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
     * @param V1\RespondActivityTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCompleted(V1\RespondActivityTaskCompletedRequest $arg, ContextInterface $ctx = null) : V1\RespondActivityTaskCompletedResponse
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
     * @param V1\RespondActivityTaskCompletedByIdRequest
     * $arg
     * @param ContextInterface|null $ctx
     * @return
     * V1\RespondActivityTaskCompletedByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCompletedById(V1\RespondActivityTaskCompletedByIdRequest $arg, ContextInterface $ctx = null) : V1\RespondActivityTaskCompletedByIdResponse
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
     * @param V1\RespondActivityTaskFailedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskFailedResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskFailed(V1\RespondActivityTaskFailedRequest $arg, ContextInterface $ctx = null) : V1\RespondActivityTaskFailedResponse
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
     * @param V1\RespondActivityTaskFailedByIdRequest
     * $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskFailedByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskFailedById(V1\RespondActivityTaskFailedByIdRequest $arg, ContextInterface $ctx = null) : V1\RespondActivityTaskFailedByIdResponse
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
     * @param V1\RespondActivityTaskCanceledRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCanceledResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCanceled(V1\RespondActivityTaskCanceledRequest $arg, ContextInterface $ctx = null) : V1\RespondActivityTaskCanceledResponse
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
     * @param V1\RespondActivityTaskCanceledByIdRequest
     * $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCanceledByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCanceledById(V1\RespondActivityTaskCanceledByIdRequest $arg, ContextInterface $ctx = null) : V1\RespondActivityTaskCanceledByIdResponse
    {
        return $this->invoke("RespondActivityTaskCanceledById", $arg, $ctx);
    }

    /**
     * RequestCancelWorkflowExecution is called by workers when they want to request
     * cancellation of
     * a workflow execution.
     *
     * This result in a new `WORKFLOW_EXECUTION_CANCEL_REQUESTED` event being written
     * to the
     * workflow history and a new workflow task created for the workflow. Fails with
     * `NotFound` if
     * the workflow is already completed or doesn't exist.
     *
     * @param V1\RequestCancelWorkflowExecutionRequest
     * $arg
     * @param ContextInterface|null $ctx
     * @return V1\RequestCancelWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function RequestCancelWorkflowExecution(V1\RequestCancelWorkflowExecutionRequest $arg, ContextInterface $ctx = null) : V1\RequestCancelWorkflowExecutionResponse
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
     * @param V1\SignalWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\SignalWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function SignalWorkflowExecution(V1\SignalWorkflowExecutionRequest $arg, ContextInterface $ctx = null) : V1\SignalWorkflowExecutionResponse
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
     * @param V1\SignalWithStartWorkflowExecutionRequest
     * $arg
     * @param ContextInterface|null $ctx
     * @return
     * V1\SignalWithStartWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function SignalWithStartWorkflowExecution(V1\SignalWithStartWorkflowExecutionRequest $arg, ContextInterface $ctx = null) : V1\SignalWithStartWorkflowExecutionResponse
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
     * @param V1\ResetWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ResetWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function ResetWorkflowExecution(V1\ResetWorkflowExecutionRequest $arg, ContextInterface $ctx = null) : V1\ResetWorkflowExecutionResponse
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
     * @param V1\TerminateWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\TerminateWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function TerminateWorkflowExecution(V1\TerminateWorkflowExecutionRequest $arg, ContextInterface $ctx = null) : V1\TerminateWorkflowExecutionResponse
    {
        return $this->invoke("TerminateWorkflowExecution", $arg, $ctx);
    }

    /**
     * ListOpenWorkflowExecutions is a visibility API to list the open executions in a
     * specific namespace.
     *
     * @param V1\ListOpenWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListOpenWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListOpenWorkflowExecutions(V1\ListOpenWorkflowExecutionsRequest $arg, ContextInterface $ctx = null) : V1\ListOpenWorkflowExecutionsResponse
    {
        return $this->invoke("ListOpenWorkflowExecutions", $arg, $ctx);
    }

    /**
     * ListClosedWorkflowExecutions is a visibility API to list the closed executions
     * in a specific namespace.
     *
     * @param V1\ListClosedWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListClosedWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListClosedWorkflowExecutions(V1\ListClosedWorkflowExecutionsRequest $arg, ContextInterface $ctx = null) : V1\ListClosedWorkflowExecutionsResponse
    {
        return $this->invoke("ListClosedWorkflowExecutions", $arg, $ctx);
    }

    /**
     * ListWorkflowExecutions is a visibility API to list workflow executions in a
     * specific namespace.
     *
     * @param V1\ListWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListWorkflowExecutions(V1\ListWorkflowExecutionsRequest $arg, ContextInterface $ctx = null) : V1\ListWorkflowExecutionsResponse
    {
        return $this->invoke("ListWorkflowExecutions", $arg, $ctx);
    }

    /**
     * ListArchivedWorkflowExecutions is a visibility API to list archived workflow
     * executions in a specific namespace.
     *
     * @param V1\ListArchivedWorkflowExecutionsRequest
     * $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListArchivedWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListArchivedWorkflowExecutions(V1\ListArchivedWorkflowExecutionsRequest $arg, ContextInterface $ctx = null) : V1\ListArchivedWorkflowExecutionsResponse
    {
        return $this->invoke("ListArchivedWorkflowExecutions", $arg, $ctx);
    }

    /**
     * ScanWorkflowExecutions is a visibility API to list large amount of workflow
     * executions in a specific namespace without order.
     *
     * @param V1\ScanWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ScanWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ScanWorkflowExecutions(V1\ScanWorkflowExecutionsRequest $arg, ContextInterface $ctx = null) : V1\ScanWorkflowExecutionsResponse
    {
        return $this->invoke("ScanWorkflowExecutions", $arg, $ctx);
    }

    /**
     * CountWorkflowExecutions is a visibility API to count of workflow executions in a
     * specific namespace.
     *
     * @param V1\CountWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\CountWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function CountWorkflowExecutions(V1\CountWorkflowExecutionsRequest $arg, ContextInterface $ctx = null) : V1\CountWorkflowExecutionsResponse
    {
        return $this->invoke("CountWorkflowExecutions", $arg, $ctx);
    }

    /**
     * GetSearchAttributes is a visibility API to get all legal keys that could be used
     * in list APIs
     *
     * @param V1\GetSearchAttributesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetSearchAttributesResponse
     * @throws ServiceClientException
     */
    public function GetSearchAttributes(V1\GetSearchAttributesRequest $arg, ContextInterface $ctx = null) : V1\GetSearchAttributesResponse
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
     * @param V1\RespondQueryTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondQueryTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondQueryTaskCompleted(V1\RespondQueryTaskCompletedRequest $arg, ContextInterface $ctx = null) : V1\RespondQueryTaskCompletedResponse
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
     * @param V1\ResetStickyTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ResetStickyTaskQueueResponse
     * @throws ServiceClientException
     */
    public function ResetStickyTaskQueue(V1\ResetStickyTaskQueueRequest $arg, ContextInterface $ctx = null) : V1\ResetStickyTaskQueueResponse
    {
        return $this->invoke("ResetStickyTaskQueue", $arg, $ctx);
    }

    /**
     * QueryWorkflow requests a query be executed for a specified workflow execution.
     *
     * @param V1\QueryWorkflowRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\QueryWorkflowResponse
     * @throws ServiceClientException
     */
    public function QueryWorkflow(V1\QueryWorkflowRequest $arg, ContextInterface $ctx = null) : V1\QueryWorkflowResponse
    {
        return $this->invoke("QueryWorkflow", $arg, $ctx);
    }

    /**
     * DescribeWorkflowExecution returns information about the specified workflow
     * execution.
     *
     * @param V1\DescribeWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function DescribeWorkflowExecution(V1\DescribeWorkflowExecutionRequest $arg, ContextInterface $ctx = null) : V1\DescribeWorkflowExecutionResponse
    {
        return $this->invoke("DescribeWorkflowExecution", $arg, $ctx);
    }

    /**
     * DescribeTaskQueue returns information about the target task queue.
     *
     * @param V1\DescribeTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeTaskQueueResponse
     * @throws ServiceClientException
     */
    public function DescribeTaskQueue(V1\DescribeTaskQueueRequest $arg, ContextInterface $ctx = null) : V1\DescribeTaskQueueResponse
    {
        return $this->invoke("DescribeTaskQueue", $arg, $ctx);
    }

    /**
     * GetClusterInfo returns information about temporal cluster
     *
     * @param V1\GetClusterInfoRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetClusterInfoResponse
     * @throws ServiceClientException
     */
    public function GetClusterInfo(V1\GetClusterInfoRequest $arg, ContextInterface $ctx = null) : V1\GetClusterInfoResponse
    {
        return $this->invoke("GetClusterInfo", $arg, $ctx);
    }

    /**
     * GetSystemInfo returns information about the system.
     *
     * @param V1\GetSystemInfoRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetSystemInfoResponse
     * @throws ServiceClientException
     */
    public function GetSystemInfo(V1\GetSystemInfoRequest $arg, ContextInterface $ctx = null) : V1\GetSystemInfoResponse
    {
        return $this->invoke("GetSystemInfo", $arg, $ctx);
    }

    /**
     * @param V1\ListTaskQueuePartitionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListTaskQueuePartitionsResponse
     * @throws ServiceClientException
     */
    public function ListTaskQueuePartitions(V1\ListTaskQueuePartitionsRequest $arg, ContextInterface $ctx = null) : V1\ListTaskQueuePartitionsResponse
    {
        return $this->invoke("ListTaskQueuePartitions", $arg, $ctx);
    }
}

