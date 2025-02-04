<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Api\Workflowservice\V1;
use Temporal\Exception\Client\ServiceClientException;

interface ServiceClientInterface
{
    public function getContext(): ContextInterface;

    public function withContext(ContextInterface $context): static;

    public function withAuthKey(\Stringable|string $key): static;

    public function getConnection(): \Temporal\Client\GRPC\Connection\ConnectionInterface;

    public function getServerCapabilities(): ?\Temporal\Client\Common\ServerCapabilities;

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
    public function RegisterNamespace(V1\RegisterNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\RegisterNamespaceResponse;

    /**
     * DescribeNamespace returns the information and configuration for a registered
     * namespace.
     *
     * @param V1\DescribeNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeNamespaceResponse
     * @throws ServiceClientException
     */
    public function DescribeNamespace(V1\DescribeNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\DescribeNamespaceResponse;

    /**
     * ListNamespaces returns the information and configuration for all namespaces.
     *
     * @param V1\ListNamespacesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListNamespacesResponse
     * @throws ServiceClientException
     */
    public function ListNamespaces(V1\ListNamespacesRequest $arg, ?ContextInterface $ctx = null): V1\ListNamespacesResponse;

    /**
     * UpdateNamespace is used to update the information and configuration of a
     * registered
     * namespace.
     *
     * @param V1\UpdateNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateNamespaceResponse
     * @throws ServiceClientException
     */
    public function UpdateNamespace(V1\UpdateNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\UpdateNamespaceResponse;

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
     * @param V1\DeprecateNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DeprecateNamespaceResponse
     * @throws ServiceClientException
     */
    public function DeprecateNamespace(V1\DeprecateNamespaceRequest $arg, ?ContextInterface $ctx = null): V1\DeprecateNamespaceResponse;

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
    public function StartWorkflowExecution(V1\StartWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\StartWorkflowExecutionResponse;

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
     * @param V1\ExecuteMultiOperationRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ExecuteMultiOperationResponse
     * @throws ServiceClientException
     */
    public function ExecuteMultiOperation(V1\ExecuteMultiOperationRequest $arg, ?ContextInterface $ctx = null): V1\ExecuteMultiOperationResponse;

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
    public function GetWorkflowExecutionHistory(V1\GetWorkflowExecutionHistoryRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkflowExecutionHistoryResponse;

    /**
     * GetWorkflowExecutionHistoryReverse returns the history of specified workflow
     * execution in reverse
     * order (starting from last event). Fails with`NotFound` if the specified workflow
     * execution is
     * unknown to the service.
     *
     * @param V1\GetWorkflowExecutionHistoryReverseRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetWorkflowExecutionHistoryReverseResponse
     * @throws ServiceClientException
     */
    public function GetWorkflowExecutionHistoryReverse(V1\GetWorkflowExecutionHistoryReverseRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkflowExecutionHistoryReverseResponse;

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
     * @param V1\PollWorkflowTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PollWorkflowTaskQueueResponse
     * @throws ServiceClientException
     */
    public function PollWorkflowTaskQueue(V1\PollWorkflowTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\PollWorkflowTaskQueueResponse;

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
     * @param V1\RespondWorkflowTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondWorkflowTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondWorkflowTaskCompleted(V1\RespondWorkflowTaskCompletedRequest $arg, ?ContextInterface $ctx = null): V1\RespondWorkflowTaskCompletedResponse;

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
     * @param V1\RespondWorkflowTaskFailedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondWorkflowTaskFailedResponse
     * @throws ServiceClientException
     */
    public function RespondWorkflowTaskFailed(V1\RespondWorkflowTaskFailedRequest $arg, ?ContextInterface $ctx = null): V1\RespondWorkflowTaskFailedResponse;

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
     * @param V1\PollActivityTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PollActivityTaskQueueResponse
     * @throws ServiceClientException
     */
    public function PollActivityTaskQueue(V1\PollActivityTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\PollActivityTaskQueueResponse;

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
    public function RecordActivityTaskHeartbeat(V1\RecordActivityTaskHeartbeatRequest $arg, ?ContextInterface $ctx = null): V1\RecordActivityTaskHeartbeatResponse;

    /**
     * See `RecordActivityTaskHeartbeat`. This version allows clients to record
     * heartbeats by
     * namespace/workflow id/activity id instead of task token.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @param V1\RecordActivityTaskHeartbeatByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RecordActivityTaskHeartbeatByIdResponse
     * @throws ServiceClientException
     */
    public function RecordActivityTaskHeartbeatById(V1\RecordActivityTaskHeartbeatByIdRequest $arg, ?ContextInterface $ctx = null): V1\RecordActivityTaskHeartbeatByIdResponse;

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
    public function RespondActivityTaskCompleted(V1\RespondActivityTaskCompletedRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskCompletedResponse;

    /**
     * See `RecordActivityTaskCompleted`. This version allows clients to record
     * completions by
     * namespace/workflow id/activity id instead of task token.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @param V1\RespondActivityTaskCompletedByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCompletedByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCompletedById(V1\RespondActivityTaskCompletedByIdRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskCompletedByIdResponse;

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
    public function RespondActivityTaskFailed(V1\RespondActivityTaskFailedRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskFailedResponse;

    /**
     * See `RecordActivityTaskFailed`. This version allows clients to record failures
     * by
     * namespace/workflow id/activity id instead of task token.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @param V1\RespondActivityTaskFailedByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskFailedByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskFailedById(V1\RespondActivityTaskFailedByIdRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskFailedByIdResponse;

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
    public function RespondActivityTaskCanceled(V1\RespondActivityTaskCanceledRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskCanceledResponse;

    /**
     * See `RecordActivityTaskCanceled`. This version allows clients to record failures
     * by
     * namespace/workflow id/activity id instead of task token.
     *
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @param V1\RespondActivityTaskCanceledByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCanceledByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCanceledById(V1\RespondActivityTaskCanceledByIdRequest $arg, ?ContextInterface $ctx = null): V1\RespondActivityTaskCanceledByIdResponse;

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
     * @param V1\RequestCancelWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RequestCancelWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function RequestCancelWorkflowExecution(V1\RequestCancelWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\RequestCancelWorkflowExecutionResponse;

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
    public function SignalWorkflowExecution(V1\SignalWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\SignalWorkflowExecutionResponse;

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
     * @param V1\SignalWithStartWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\SignalWithStartWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function SignalWithStartWorkflowExecution(V1\SignalWithStartWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\SignalWithStartWorkflowExecutionResponse;

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
    public function ResetWorkflowExecution(V1\ResetWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\ResetWorkflowExecutionResponse;

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
    public function TerminateWorkflowExecution(V1\TerminateWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\TerminateWorkflowExecutionResponse;

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
     * @param V1\DeleteWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DeleteWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function DeleteWorkflowExecution(V1\DeleteWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\DeleteWorkflowExecutionResponse;

    /**
     * ListOpenWorkflowExecutions is a visibility API to list the open executions in a
     * specific namespace.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @param V1\ListOpenWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListOpenWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListOpenWorkflowExecutions(V1\ListOpenWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ListOpenWorkflowExecutionsResponse;

    /**
     * ListClosedWorkflowExecutions is a visibility API to list the closed executions
     * in a specific namespace.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @param V1\ListClosedWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListClosedWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListClosedWorkflowExecutions(V1\ListClosedWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ListClosedWorkflowExecutionsResponse;

    /**
     * ListWorkflowExecutions is a visibility API to list workflow executions in a
     * specific namespace.
     *
     * @param V1\ListWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListWorkflowExecutions(V1\ListWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ListWorkflowExecutionsResponse;

    /**
     * ListArchivedWorkflowExecutions is a visibility API to list archived workflow
     * executions in a specific namespace.
     *
     * @param V1\ListArchivedWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListArchivedWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListArchivedWorkflowExecutions(V1\ListArchivedWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ListArchivedWorkflowExecutionsResponse;

    /**
     * ScanWorkflowExecutions is a visibility API to list large amount of workflow
     * executions in a specific namespace without order.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @param V1\ScanWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ScanWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ScanWorkflowExecutions(V1\ScanWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\ScanWorkflowExecutionsResponse;

    /**
     * CountWorkflowExecutions is a visibility API to count of workflow executions in a
     * specific namespace.
     *
     * @param V1\CountWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\CountWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function CountWorkflowExecutions(V1\CountWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): V1\CountWorkflowExecutionsResponse;

    /**
     * GetSearchAttributes is a visibility API to get all legal keys that could be used
     * in list APIs
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose this search attribute API to HTTP (but
     * may expose on OperatorService). --)
     *
     * @param V1\GetSearchAttributesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetSearchAttributesResponse
     * @throws ServiceClientException
     */
    public function GetSearchAttributes(V1\GetSearchAttributesRequest $arg, ?ContextInterface $ctx = null): V1\GetSearchAttributesResponse;

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
     * @param V1\RespondQueryTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondQueryTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondQueryTaskCompleted(V1\RespondQueryTaskCompletedRequest $arg, ?ContextInterface $ctx = null): V1\RespondQueryTaskCompletedResponse;

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
     * @param V1\ResetStickyTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ResetStickyTaskQueueResponse
     * @throws ServiceClientException
     */
    public function ResetStickyTaskQueue(V1\ResetStickyTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\ResetStickyTaskQueueResponse;

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
     * @param V1\ShutdownWorkerRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ShutdownWorkerResponse
     * @throws ServiceClientException
     */
    public function ShutdownWorker(V1\ShutdownWorkerRequest $arg, ?ContextInterface $ctx = null): V1\ShutdownWorkerResponse;

    /**
     * QueryWorkflow requests a query be executed for a specified workflow execution.
     *
     * @param V1\QueryWorkflowRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\QueryWorkflowResponse
     * @throws ServiceClientException
     */
    public function QueryWorkflow(V1\QueryWorkflowRequest $arg, ?ContextInterface $ctx = null): V1\QueryWorkflowResponse;

    /**
     * DescribeWorkflowExecution returns information about the specified workflow
     * execution.
     *
     * @param V1\DescribeWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function DescribeWorkflowExecution(V1\DescribeWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\DescribeWorkflowExecutionResponse;

    /**
     * DescribeTaskQueue returns the following information about the target task queue,
     * broken down by Build ID:
     * - List of pollers
     * - Workflow Reachability status
     * - Backlog info for Workflow and/or Activity tasks
     *
     * @param V1\DescribeTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeTaskQueueResponse
     * @throws ServiceClientException
     */
    public function DescribeTaskQueue(V1\DescribeTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\DescribeTaskQueueResponse;

    /**
     * GetClusterInfo returns information about temporal cluster
     *
     * @param V1\GetClusterInfoRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetClusterInfoResponse
     * @throws ServiceClientException
     */
    public function GetClusterInfo(V1\GetClusterInfoRequest $arg, ?ContextInterface $ctx = null): V1\GetClusterInfoResponse;

    /**
     * GetSystemInfo returns information about the system.
     *
     * @param V1\GetSystemInfoRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetSystemInfoResponse
     * @throws ServiceClientException
     */
    public function GetSystemInfo(V1\GetSystemInfoRequest $arg, ?ContextInterface $ctx = null): V1\GetSystemInfoResponse;

    /**
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose this low-level API to HTTP. --)
     *
     * @param V1\ListTaskQueuePartitionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListTaskQueuePartitionsResponse
     * @throws ServiceClientException
     */
    public function ListTaskQueuePartitions(V1\ListTaskQueuePartitionsRequest $arg, ?ContextInterface $ctx = null): V1\ListTaskQueuePartitionsResponse;

    /**
     * Creates a new schedule.
     *
     * @param V1\CreateScheduleRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\CreateScheduleResponse
     * @throws ServiceClientException
     */
    public function CreateSchedule(V1\CreateScheduleRequest $arg, ?ContextInterface $ctx = null): V1\CreateScheduleResponse;

    /**
     * Returns the schedule description and current state of an existing schedule.
     *
     * @param V1\DescribeScheduleRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeScheduleResponse
     * @throws ServiceClientException
     */
    public function DescribeSchedule(V1\DescribeScheduleRequest $arg, ?ContextInterface $ctx = null): V1\DescribeScheduleResponse;

    /**
     * Changes the configuration or state of an existing schedule.
     *
     * @param V1\UpdateScheduleRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateScheduleResponse
     * @throws ServiceClientException
     */
    public function UpdateSchedule(V1\UpdateScheduleRequest $arg, ?ContextInterface $ctx = null): V1\UpdateScheduleResponse;

    /**
     * Makes a specific change to a schedule or triggers an immediate action.
     *
     * @param V1\PatchScheduleRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PatchScheduleResponse
     * @throws ServiceClientException
     */
    public function PatchSchedule(V1\PatchScheduleRequest $arg, ?ContextInterface $ctx = null): V1\PatchScheduleResponse;

    /**
     * Lists matching times within a range.
     *
     * @param V1\ListScheduleMatchingTimesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListScheduleMatchingTimesResponse
     * @throws ServiceClientException
     */
    public function ListScheduleMatchingTimes(V1\ListScheduleMatchingTimesRequest $arg, ?ContextInterface $ctx = null): V1\ListScheduleMatchingTimesResponse;

    /**
     * Deletes a schedule, removing it from the system.
     *
     * @param V1\DeleteScheduleRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DeleteScheduleResponse
     * @throws ServiceClientException
     */
    public function DeleteSchedule(V1\DeleteScheduleRequest $arg, ?ContextInterface $ctx = null): V1\DeleteScheduleResponse;

    /**
     * List all schedules in a namespace.
     *
     * @param V1\ListSchedulesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListSchedulesResponse
     * @throws ServiceClientException
     */
    public function ListSchedules(V1\ListSchedulesRequest $arg, ?ContextInterface $ctx = null): V1\ListSchedulesResponse;

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
     * @param V1\UpdateWorkerBuildIdCompatibilityRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateWorkerBuildIdCompatibilityResponse
     * @throws ServiceClientException
     */
    public function UpdateWorkerBuildIdCompatibility(V1\UpdateWorkerBuildIdCompatibilityRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkerBuildIdCompatibilityResponse;

    /**
     * Deprecated. Use `GetWorkerVersioningRules`.
     * Fetches the worker build id versioning sets for a task queue.
     *
     * @param V1\GetWorkerBuildIdCompatibilityRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetWorkerBuildIdCompatibilityResponse
     * @throws ServiceClientException
     */
    public function GetWorkerBuildIdCompatibility(V1\GetWorkerBuildIdCompatibilityRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkerBuildIdCompatibilityResponse;

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
     * @param V1\UpdateWorkerVersioningRulesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateWorkerVersioningRulesResponse
     * @throws ServiceClientException
     */
    public function UpdateWorkerVersioningRules(V1\UpdateWorkerVersioningRulesRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkerVersioningRulesResponse;

    /**
     * Fetches the Build ID assignment and redirect rules for a Task Queue.
     * WARNING: Worker Versioning is not yet stable and the API and behavior may change
     * incompatibly.
     *
     * @param V1\GetWorkerVersioningRulesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetWorkerVersioningRulesResponse
     * @throws ServiceClientException
     */
    public function GetWorkerVersioningRules(V1\GetWorkerVersioningRulesRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkerVersioningRulesResponse;

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
     * @param V1\GetWorkerTaskReachabilityRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetWorkerTaskReachabilityResponse
     * @throws ServiceClientException
     */
    public function GetWorkerTaskReachability(V1\GetWorkerTaskReachabilityRequest $arg, ?ContextInterface $ctx = null): V1\GetWorkerTaskReachabilityResponse;

    /**
     * Describes a worker deployment.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @param V1\DescribeDeploymentRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeDeploymentResponse
     * @throws ServiceClientException
     */
    public function DescribeDeployment(V1\DescribeDeploymentRequest $arg, ?ContextInterface $ctx = null): V1\DescribeDeploymentResponse;

    /**
     * Lists worker deployments in the namespace. Optionally can filter based on
     * deployment series
     * name.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @param V1\ListDeploymentsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListDeploymentsResponse
     * @throws ServiceClientException
     */
    public function ListDeployments(V1\ListDeploymentsRequest $arg, ?ContextInterface $ctx = null): V1\ListDeploymentsResponse;

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
     *
     * @param V1\GetDeploymentReachabilityRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetDeploymentReachabilityResponse
     * @throws ServiceClientException
     */
    public function GetDeploymentReachability(V1\GetDeploymentReachabilityRequest $arg, ?ContextInterface $ctx = null): V1\GetDeploymentReachabilityResponse;

    /**
     * Returns the current deployment (and its info) for a given deployment series.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @param V1\GetCurrentDeploymentRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetCurrentDeploymentResponse
     * @throws ServiceClientException
     */
    public function GetCurrentDeployment(V1\GetCurrentDeploymentRequest $arg, ?ContextInterface $ctx = null): V1\GetCurrentDeploymentResponse;

    /**
     * Sets a deployment as the current deployment for its deployment series. Can
     * optionally update
     * the metadata of the deployment as well.
     * Experimental. This API might significantly change or be removed in a future
     * release.
     *
     * @param V1\SetCurrentDeploymentRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\SetCurrentDeploymentResponse
     * @throws ServiceClientException
     */
    public function SetCurrentDeployment(V1\SetCurrentDeploymentRequest $arg, ?ContextInterface $ctx = null): V1\SetCurrentDeploymentResponse;

    /**
     * Invokes the specified Update function on user Workflow code.
     *
     * @param V1\UpdateWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function UpdateWorkflowExecution(V1\UpdateWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkflowExecutionResponse;

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
     * @param V1\PollWorkflowExecutionUpdateRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PollWorkflowExecutionUpdateResponse
     * @throws ServiceClientException
     */
    public function PollWorkflowExecutionUpdate(V1\PollWorkflowExecutionUpdateRequest $arg, ?ContextInterface $ctx = null): V1\PollWorkflowExecutionUpdateResponse;

    /**
     * StartBatchOperation starts a new batch operation
     *
     * @param V1\StartBatchOperationRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\StartBatchOperationResponse
     * @throws ServiceClientException
     */
    public function StartBatchOperation(V1\StartBatchOperationRequest $arg, ?ContextInterface $ctx = null): V1\StartBatchOperationResponse;

    /**
     * StopBatchOperation stops a batch operation
     *
     * @param V1\StopBatchOperationRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\StopBatchOperationResponse
     * @throws ServiceClientException
     */
    public function StopBatchOperation(V1\StopBatchOperationRequest $arg, ?ContextInterface $ctx = null): V1\StopBatchOperationResponse;

    /**
     * DescribeBatchOperation returns the information about a batch operation
     *
     * @param V1\DescribeBatchOperationRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeBatchOperationResponse
     * @throws ServiceClientException
     */
    public function DescribeBatchOperation(V1\DescribeBatchOperationRequest $arg, ?ContextInterface $ctx = null): V1\DescribeBatchOperationResponse;

    /**
     * ListBatchOperations returns a list of batch operations
     *
     * @param V1\ListBatchOperationsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListBatchOperationsResponse
     * @throws ServiceClientException
     */
    public function ListBatchOperations(V1\ListBatchOperationsRequest $arg, ?ContextInterface $ctx = null): V1\ListBatchOperationsResponse;

    /**
     * PollNexusTaskQueue is a long poll call used by workers to receive Nexus tasks.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @param V1\PollNexusTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PollNexusTaskQueueResponse
     * @throws ServiceClientException
     */
    public function PollNexusTaskQueue(V1\PollNexusTaskQueueRequest $arg, ?ContextInterface $ctx = null): V1\PollNexusTaskQueueResponse;

    /**
     * RespondNexusTaskCompleted is called by workers to respond to Nexus tasks
     * received via PollNexusTaskQueue.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @param V1\RespondNexusTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondNexusTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondNexusTaskCompleted(V1\RespondNexusTaskCompletedRequest $arg, ?ContextInterface $ctx = null): V1\RespondNexusTaskCompletedResponse;

    /**
     * RespondNexusTaskFailed is called by workers to fail Nexus tasks received via
     * PollNexusTaskQueue.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @param V1\RespondNexusTaskFailedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondNexusTaskFailedResponse
     * @throws ServiceClientException
     */
    public function RespondNexusTaskFailed(V1\RespondNexusTaskFailedRequest $arg, ?ContextInterface $ctx = null): V1\RespondNexusTaskFailedResponse;

    /**
     * UpdateActivityOptionsById is called by the client to update the options of an
     * activity
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @param V1\UpdateActivityOptionsByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateActivityOptionsByIdResponse
     * @throws ServiceClientException
     */
    public function UpdateActivityOptionsById(V1\UpdateActivityOptionsByIdRequest $arg, ?ContextInterface $ctx = null): V1\UpdateActivityOptionsByIdResponse;

    /**
     * UpdateWorkflowExecutionOptions partially updates the WorkflowExecutionOptions of
     * an existing workflow execution.
     *
     * @param V1\UpdateWorkflowExecutionOptionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateWorkflowExecutionOptionsResponse
     * @throws ServiceClientException
     */
    public function UpdateWorkflowExecutionOptions(V1\UpdateWorkflowExecutionOptionsRequest $arg, ?ContextInterface $ctx = null): V1\UpdateWorkflowExecutionOptionsResponse;

    /**
     * PauseActivityById pauses the execution of an activity specified by its ID.
     * Returns a `NotFound` error if there is no pending activity with the provided ID.
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
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @param V1\PauseActivityByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PauseActivityByIdResponse
     * @throws ServiceClientException
     */
    public function PauseActivityById(V1\PauseActivityByIdRequest $arg, ?ContextInterface $ctx = null): V1\PauseActivityByIdResponse;

    /**
     * UnpauseActivityById unpauses the execution of an activity specified by its ID.
     * Returns a `NotFound` error if there is no pending activity with the provided ID.
     * There are two 'modes' of unpausing an activity:
     * 'resume' - If the activity is paused, it will be resumed and scheduled for
     * execution.
     * If the activity is currently running Unpause with 'resume' has no effect.
     * if 'no_wait' flag is set and the activity is waiting, the activity will be
     * scheduled immediately.
     * 'reset' - If the activity is paused, it will be reset to its initial state and
     * (depending on parameters) scheduled for execution.
     * If the activity is currently running, Unpause with 'reset' will reset the number
     * of attempts.
     * if 'no_wait' flag is set, the activity will be scheduled immediately.
     * if 'reset_heartbeats' flag is set, the activity heartbeat timer and heartbeats
     * will be reset.
     * If the activity is in waiting for retry and past it retry timeout, it will be
     * scheduled immediately.
     * Once the activity is unpaused, all timeout timers will be regenerated.
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @param V1\UnpauseActivityByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UnpauseActivityByIdResponse
     * @throws ServiceClientException
     */
    public function UnpauseActivityById(V1\UnpauseActivityByIdRequest $arg, ?ContextInterface $ctx = null): V1\UnpauseActivityByIdResponse;

    /**
     * ResetActivityById unpauses the execution of an activity specified by its ID.
     * Returns a `NotFound` error if there is no pending activity with the provided ID.
     * Resetting an activity means:
     * number of attempts will be reset to 0.
     * activity timeouts will be resetted.
     * If the activity currently running:
     * if 'no_wait' flag is provided, a new instance of the activity will be scheduled
     * immediately.
     * if 'no_wait' flag is not provided, a new instance of the  activity will be
     * scheduled after current instance completes if needed.
     * If 'reset_heartbeats' flag is set, the activity heartbeat timer and heartbeats
     * will be reset.
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     *
     * @param V1\ResetActivityByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ResetActivityByIdResponse
     * @throws ServiceClientException
     */
    public function ResetActivityById(V1\ResetActivityByIdRequest $arg, ?ContextInterface $ctx = null): V1\ResetActivityByIdResponse;

    /**
     * Close the communication channel associated with this stub.
     */
    public function close(): void;
}
