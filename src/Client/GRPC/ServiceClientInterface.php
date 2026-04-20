<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC;

use Temporal\Api\Workflowservice\V1\CountWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\CountWorkflowExecutionsResponse;
use Temporal\Api\Workflowservice\V1\CreateScheduleRequest;
use Temporal\Api\Workflowservice\V1\CreateScheduleResponse;
use Temporal\Api\Workflowservice\V1\CreateWorkflowRuleRequest;
use Temporal\Api\Workflowservice\V1\CreateWorkflowRuleResponse;
use Temporal\Api\Workflowservice\V1\DeleteScheduleRequest;
use Temporal\Api\Workflowservice\V1\DeleteScheduleResponse;
use Temporal\Api\Workflowservice\V1\DeleteWorkerDeploymentRequest;
use Temporal\Api\Workflowservice\V1\DeleteWorkerDeploymentResponse;
use Temporal\Api\Workflowservice\V1\DeleteWorkerDeploymentVersionRequest;
use Temporal\Api\Workflowservice\V1\DeleteWorkerDeploymentVersionResponse;
use Temporal\Api\Workflowservice\V1\DeleteWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\DeleteWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\DeleteWorkflowRuleRequest;
use Temporal\Api\Workflowservice\V1\DeleteWorkflowRuleResponse;
use Temporal\Api\Workflowservice\V1\DeprecateNamespaceRequest;
use Temporal\Api\Workflowservice\V1\DeprecateNamespaceResponse;
use Temporal\Api\Workflowservice\V1\DescribeBatchOperationRequest;
use Temporal\Api\Workflowservice\V1\DescribeBatchOperationResponse;
use Temporal\Api\Workflowservice\V1\DescribeDeploymentRequest;
use Temporal\Api\Workflowservice\V1\DescribeDeploymentResponse;
use Temporal\Api\Workflowservice\V1\DescribeNamespaceRequest;
use Temporal\Api\Workflowservice\V1\DescribeNamespaceResponse;
use Temporal\Api\Workflowservice\V1\DescribeScheduleRequest;
use Temporal\Api\Workflowservice\V1\DescribeScheduleResponse;
use Temporal\Api\Workflowservice\V1\DescribeTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\DescribeTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\DescribeWorkerDeploymentRequest;
use Temporal\Api\Workflowservice\V1\DescribeWorkerDeploymentResponse;
use Temporal\Api\Workflowservice\V1\DescribeWorkerDeploymentVersionRequest;
use Temporal\Api\Workflowservice\V1\DescribeWorkerDeploymentVersionResponse;
use Temporal\Api\Workflowservice\V1\DescribeWorkerRequest;
use Temporal\Api\Workflowservice\V1\DescribeWorkerResponse;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowRuleRequest;
use Temporal\Api\Workflowservice\V1\DescribeWorkflowRuleResponse;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationRequest;
use Temporal\Api\Workflowservice\V1\ExecuteMultiOperationResponse;
use Temporal\Api\Workflowservice\V1\FetchWorkerConfigRequest;
use Temporal\Api\Workflowservice\V1\FetchWorkerConfigResponse;
use Temporal\Api\Workflowservice\V1\GetClusterInfoRequest;
use Temporal\Api\Workflowservice\V1\GetClusterInfoResponse;
use Temporal\Api\Workflowservice\V1\GetCurrentDeploymentRequest;
use Temporal\Api\Workflowservice\V1\GetCurrentDeploymentResponse;
use Temporal\Api\Workflowservice\V1\GetDeploymentReachabilityRequest;
use Temporal\Api\Workflowservice\V1\GetDeploymentReachabilityResponse;
use Temporal\Api\Workflowservice\V1\GetSearchAttributesRequest;
use Temporal\Api\Workflowservice\V1\GetSearchAttributesResponse;
use Temporal\Api\Workflowservice\V1\GetSystemInfoRequest;
use Temporal\Api\Workflowservice\V1\GetSystemInfoResponse;
use Temporal\Api\Workflowservice\V1\GetWorkerBuildIdCompatibilityRequest;
use Temporal\Api\Workflowservice\V1\GetWorkerBuildIdCompatibilityResponse;
use Temporal\Api\Workflowservice\V1\GetWorkerTaskReachabilityRequest;
use Temporal\Api\Workflowservice\V1\GetWorkerTaskReachabilityResponse;
use Temporal\Api\Workflowservice\V1\GetWorkerVersioningRulesRequest;
use Temporal\Api\Workflowservice\V1\GetWorkerVersioningRulesResponse;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryReverseRequest;
use Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryReverseResponse;
use Temporal\Api\Workflowservice\V1\ListArchivedWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\ListArchivedWorkflowExecutionsResponse;
use Temporal\Api\Workflowservice\V1\ListBatchOperationsRequest;
use Temporal\Api\Workflowservice\V1\ListBatchOperationsResponse;
use Temporal\Api\Workflowservice\V1\ListClosedWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\ListClosedWorkflowExecutionsResponse;
use Temporal\Api\Workflowservice\V1\ListDeploymentsRequest;
use Temporal\Api\Workflowservice\V1\ListDeploymentsResponse;
use Temporal\Api\Workflowservice\V1\ListNamespacesRequest;
use Temporal\Api\Workflowservice\V1\ListNamespacesResponse;
use Temporal\Api\Workflowservice\V1\ListOpenWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\ListOpenWorkflowExecutionsResponse;
use Temporal\Api\Workflowservice\V1\ListScheduleMatchingTimesRequest;
use Temporal\Api\Workflowservice\V1\ListScheduleMatchingTimesResponse;
use Temporal\Api\Workflowservice\V1\ListSchedulesRequest;
use Temporal\Api\Workflowservice\V1\ListSchedulesResponse;
use Temporal\Api\Workflowservice\V1\ListTaskQueuePartitionsRequest;
use Temporal\Api\Workflowservice\V1\ListTaskQueuePartitionsResponse;
use Temporal\Api\Workflowservice\V1\ListWorkerDeploymentsRequest;
use Temporal\Api\Workflowservice\V1\ListWorkerDeploymentsResponse;
use Temporal\Api\Workflowservice\V1\ListWorkersRequest;
use Temporal\Api\Workflowservice\V1\ListWorkersResponse;
use Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsResponse;
use Temporal\Api\Workflowservice\V1\ListWorkflowRulesRequest;
use Temporal\Api\Workflowservice\V1\ListWorkflowRulesResponse;
use Temporal\Api\Workflowservice\V1\PatchScheduleRequest;
use Temporal\Api\Workflowservice\V1\PatchScheduleResponse;
use Temporal\Api\Workflowservice\V1\PauseActivityRequest;
use Temporal\Api\Workflowservice\V1\PauseActivityResponse;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\PollActivityTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\PollNexusTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\PollNexusTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\PollWorkflowExecutionUpdateRequest;
use Temporal\Api\Workflowservice\V1\PollWorkflowExecutionUpdateResponse;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\QueryWorkflowRequest;
use Temporal\Api\Workflowservice\V1\QueryWorkflowResponse;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdRequest;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdResponse;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatRequest;
use Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatResponse;
use Temporal\Api\Workflowservice\V1\RecordWorkerHeartbeatRequest;
use Temporal\Api\Workflowservice\V1\RecordWorkerHeartbeatResponse;
use Temporal\Api\Workflowservice\V1\RegisterNamespaceRequest;
use Temporal\Api\Workflowservice\V1\RegisterNamespaceResponse;
use Temporal\Api\Workflowservice\V1\RequestCancelWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\RequestCancelWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\ResetActivityRequest;
use Temporal\Api\Workflowservice\V1\ResetActivityResponse;
use Temporal\Api\Workflowservice\V1\ResetStickyTaskQueueRequest;
use Temporal\Api\Workflowservice\V1\ResetStickyTaskQueueResponse;
use Temporal\Api\Workflowservice\V1\ResetWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\ResetWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdResponse;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedResponse;
use Temporal\Api\Workflowservice\V1\RespondNexusTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondNexusTaskCompletedResponse;
use Temporal\Api\Workflowservice\V1\RespondNexusTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\RespondNexusTaskFailedResponse;
use Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedResponse;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedResponse;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedRequest;
use Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedResponse;
use Temporal\Api\Workflowservice\V1\ScanWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\ScanWorkflowExecutionsResponse;
use Temporal\Api\Workflowservice\V1\SetCurrentDeploymentRequest;
use Temporal\Api\Workflowservice\V1\SetCurrentDeploymentResponse;
use Temporal\Api\Workflowservice\V1\SetWorkerDeploymentCurrentVersionRequest;
use Temporal\Api\Workflowservice\V1\SetWorkerDeploymentCurrentVersionResponse;
use Temporal\Api\Workflowservice\V1\SetWorkerDeploymentManagerRequest;
use Temporal\Api\Workflowservice\V1\SetWorkerDeploymentManagerResponse;
use Temporal\Api\Workflowservice\V1\SetWorkerDeploymentRampingVersionRequest;
use Temporal\Api\Workflowservice\V1\SetWorkerDeploymentRampingVersionResponse;
use Temporal\Api\Workflowservice\V1\ShutdownWorkerRequest;
use Temporal\Api\Workflowservice\V1\ShutdownWorkerResponse;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\StartBatchOperationRequest;
use Temporal\Api\Workflowservice\V1\StartBatchOperationResponse;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\StopBatchOperationRequest;
use Temporal\Api\Workflowservice\V1\StopBatchOperationResponse;
use Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionResponse;
use Temporal\Api\Workflowservice\V1\TriggerWorkflowRuleRequest;
use Temporal\Api\Workflowservice\V1\TriggerWorkflowRuleResponse;
use Temporal\Api\Workflowservice\V1\UnpauseActivityRequest;
use Temporal\Api\Workflowservice\V1\UnpauseActivityResponse;
use Temporal\Api\Workflowservice\V1\UpdateActivityOptionsRequest;
use Temporal\Api\Workflowservice\V1\UpdateActivityOptionsResponse;
use Temporal\Api\Workflowservice\V1\UpdateNamespaceRequest;
use Temporal\Api\Workflowservice\V1\UpdateNamespaceResponse;
use Temporal\Api\Workflowservice\V1\UpdateScheduleRequest;
use Temporal\Api\Workflowservice\V1\UpdateScheduleResponse;
use Temporal\Api\Workflowservice\V1\UpdateTaskQueueConfigRequest;
use Temporal\Api\Workflowservice\V1\UpdateTaskQueueConfigResponse;
use Temporal\Api\Workflowservice\V1\UpdateWorkerBuildIdCompatibilityRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkerBuildIdCompatibilityResponse;
use Temporal\Api\Workflowservice\V1\UpdateWorkerConfigRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkerConfigResponse;
use Temporal\Api\Workflowservice\V1\UpdateWorkerDeploymentVersionMetadataRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkerDeploymentVersionMetadataResponse;
use Temporal\Api\Workflowservice\V1\UpdateWorkerVersioningRulesRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkerVersioningRulesResponse;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionOptionsRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionOptionsResponse;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\UpdateWorkflowExecutionResponse;
use Temporal\Exception\Client\ServiceClientException;
use Temporal\Client\Common\ServerCapabilities;

interface ServiceClientInterface extends GrpcClientInterface
{
    public function getServerCapabilities(): ?ServerCapabilities;

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
    public function RegisterNamespace(RegisterNamespaceRequest $arg, ?ContextInterface $ctx = null): RegisterNamespaceResponse;

    /**
     * DescribeNamespace returns the information and configuration for a registered
     * namespace.
     *
     * @throws ServiceClientException
     */
    public function DescribeNamespace(DescribeNamespaceRequest $arg, ?ContextInterface $ctx = null): DescribeNamespaceResponse;

    /**
     * ListNamespaces returns the information and configuration for all namespaces.
     *
     * @throws ServiceClientException
     */
    public function ListNamespaces(ListNamespacesRequest $arg, ?ContextInterface $ctx = null): ListNamespacesResponse;

    /**
     * UpdateNamespace is used to update the information and configuration of a
     * registered
     * namespace.
     *
     * @throws ServiceClientException
     */
    public function UpdateNamespace(UpdateNamespaceRequest $arg, ?ContextInterface $ctx = null): UpdateNamespaceResponse;

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
    public function DeprecateNamespace(DeprecateNamespaceRequest $arg, ?ContextInterface $ctx = null): DeprecateNamespaceResponse;

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
    public function StartWorkflowExecution(StartWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): StartWorkflowExecutionResponse;

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
     * @throws ServiceClientException
     */
    public function ExecuteMultiOperation(ExecuteMultiOperationRequest $arg, ?ContextInterface $ctx = null): ExecuteMultiOperationResponse;

    /**
     * GetWorkflowExecutionHistory returns the history of specified workflow execution.
     * Fails with
     * `NotFound` if the specified workflow execution is unknown to the service.
     *
     * @throws ServiceClientException
     */
    public function GetWorkflowExecutionHistory(GetWorkflowExecutionHistoryRequest $arg, ?ContextInterface $ctx = null): GetWorkflowExecutionHistoryResponse;

    /**
     * GetWorkflowExecutionHistoryReverse returns the history of specified workflow
     * execution in reverse
     * order (starting from last event). Fails with`NotFound` if the specified workflow
     * execution is
     * unknown to the service.
     *
     * @throws ServiceClientException
     */
    public function GetWorkflowExecutionHistoryReverse(GetWorkflowExecutionHistoryReverseRequest $arg, ?ContextInterface $ctx = null): GetWorkflowExecutionHistoryReverseResponse;

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
    public function PollWorkflowTaskQueue(PollWorkflowTaskQueueRequest $arg, ?ContextInterface $ctx = null): PollWorkflowTaskQueueResponse;

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
    public function RespondWorkflowTaskCompleted(RespondWorkflowTaskCompletedRequest $arg, ?ContextInterface $ctx = null): RespondWorkflowTaskCompletedResponse;

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
    public function RespondWorkflowTaskFailed(RespondWorkflowTaskFailedRequest $arg, ?ContextInterface $ctx = null): RespondWorkflowTaskFailedResponse;

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
    public function PollActivityTaskQueue(PollActivityTaskQueueRequest $arg, ?ContextInterface $ctx = null): PollActivityTaskQueueResponse;

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
    public function RecordActivityTaskHeartbeat(RecordActivityTaskHeartbeatRequest $arg, ?ContextInterface $ctx = null): RecordActivityTaskHeartbeatResponse;

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
    public function RecordActivityTaskHeartbeatById(RecordActivityTaskHeartbeatByIdRequest $arg, ?ContextInterface $ctx = null): RecordActivityTaskHeartbeatByIdResponse;

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
    public function RespondActivityTaskCompleted(RespondActivityTaskCompletedRequest $arg, ?ContextInterface $ctx = null): RespondActivityTaskCompletedResponse;

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
    public function RespondActivityTaskCompletedById(RespondActivityTaskCompletedByIdRequest $arg, ?ContextInterface $ctx = null): RespondActivityTaskCompletedByIdResponse;

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
    public function RespondActivityTaskFailed(RespondActivityTaskFailedRequest $arg, ?ContextInterface $ctx = null): RespondActivityTaskFailedResponse;

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
    public function RespondActivityTaskFailedById(RespondActivityTaskFailedByIdRequest $arg, ?ContextInterface $ctx = null): RespondActivityTaskFailedByIdResponse;

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
    public function RespondActivityTaskCanceled(RespondActivityTaskCanceledRequest $arg, ?ContextInterface $ctx = null): RespondActivityTaskCanceledResponse;

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
    public function RespondActivityTaskCanceledById(RespondActivityTaskCanceledByIdRequest $arg, ?ContextInterface $ctx = null): RespondActivityTaskCanceledByIdResponse;

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
    public function RequestCancelWorkflowExecution(RequestCancelWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): RequestCancelWorkflowExecutionResponse;

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
    public function SignalWorkflowExecution(SignalWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): SignalWorkflowExecutionResponse;

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
    public function SignalWithStartWorkflowExecution(SignalWithStartWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): SignalWithStartWorkflowExecutionResponse;

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
    public function ResetWorkflowExecution(ResetWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): ResetWorkflowExecutionResponse;

    /**
     * TerminateWorkflowExecution terminates an existing workflow execution by
     * recording a
     * `WORKFLOW_EXECUTION_TERMINATED` event in the history and immediately terminating
     * the
     * execution instance.
     *
     * @throws ServiceClientException
     */
    public function TerminateWorkflowExecution(TerminateWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): TerminateWorkflowExecutionResponse;

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
    public function DeleteWorkflowExecution(DeleteWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): DeleteWorkflowExecutionResponse;

    /**
     * ListOpenWorkflowExecutions is a visibility API to list the open executions in a
     * specific namespace.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @throws ServiceClientException
     */
    public function ListOpenWorkflowExecutions(ListOpenWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): ListOpenWorkflowExecutionsResponse;

    /**
     * ListClosedWorkflowExecutions is a visibility API to list the closed executions
     * in a specific namespace.
     *
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @throws ServiceClientException
     */
    public function ListClosedWorkflowExecutions(ListClosedWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): ListClosedWorkflowExecutionsResponse;

    /**
     * ListWorkflowExecutions is a visibility API to list workflow executions in a
     * specific namespace.
     *
     * @throws ServiceClientException
     */
    public function ListWorkflowExecutions(ListWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): ListWorkflowExecutionsResponse;

    /**
     * ListArchivedWorkflowExecutions is a visibility API to list archived workflow
     * executions in a specific namespace.
     *
     * @throws ServiceClientException
     */
    public function ListArchivedWorkflowExecutions(ListArchivedWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): ListArchivedWorkflowExecutionsResponse;

    /**
     * ScanWorkflowExecutions _was_ a visibility API to list large amount of workflow
     * executions in a specific namespace without order.
     * It has since been deprecated in favor of `ListWorkflowExecutions` and rewritten
     * to use `ListWorkflowExecutions` internally.
     *
     * Deprecated: Replaced with `ListWorkflowExecutions`.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: HTTP users should use ListWorkflowExecutions instead. --)
     *
     * @throws ServiceClientException
     */
    public function ScanWorkflowExecutions(ScanWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): ScanWorkflowExecutionsResponse;

    /**
     * CountWorkflowExecutions is a visibility API to count of workflow executions in a
     * specific namespace.
     *
     * @throws ServiceClientException
     */
    public function CountWorkflowExecutions(CountWorkflowExecutionsRequest $arg, ?ContextInterface $ctx = null): CountWorkflowExecutionsResponse;

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
    public function GetSearchAttributes(GetSearchAttributesRequest $arg, ?ContextInterface $ctx = null): GetSearchAttributesResponse;

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
    public function RespondQueryTaskCompleted(RespondQueryTaskCompletedRequest $arg, ?ContextInterface $ctx = null): RespondQueryTaskCompletedResponse;

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
    public function ResetStickyTaskQueue(ResetStickyTaskQueueRequest $arg, ?ContextInterface $ctx = null): ResetStickyTaskQueueResponse;

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
    public function ShutdownWorker(ShutdownWorkerRequest $arg, ?ContextInterface $ctx = null): ShutdownWorkerResponse;

    /**
     * QueryWorkflow requests a query be executed for a specified workflow execution.
     *
     * @throws ServiceClientException
     */
    public function QueryWorkflow(QueryWorkflowRequest $arg, ?ContextInterface $ctx = null): QueryWorkflowResponse;

    /**
     * DescribeWorkflowExecution returns information about the specified workflow
     * execution.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorkflowExecution(DescribeWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): DescribeWorkflowExecutionResponse;

    /**
     * DescribeTaskQueue returns the following information about the target task queue,
     * broken down by Build ID:
     * - List of pollers
     * - Workflow Reachability status
     * - Backlog info for Workflow and/or Activity tasks
     *
     * @throws ServiceClientException
     */
    public function DescribeTaskQueue(DescribeTaskQueueRequest $arg, ?ContextInterface $ctx = null): DescribeTaskQueueResponse;

    /**
     * GetClusterInfo returns information about temporal cluster
     *
     * @throws ServiceClientException
     */
    public function GetClusterInfo(GetClusterInfoRequest $arg, ?ContextInterface $ctx = null): GetClusterInfoResponse;

    /**
     * GetSystemInfo returns information about the system.
     *
     * @throws ServiceClientException
     */
    public function GetSystemInfo(GetSystemInfoRequest $arg, ?ContextInterface $ctx = null): GetSystemInfoResponse;

    /**
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose this low-level API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function ListTaskQueuePartitions(ListTaskQueuePartitionsRequest $arg, ?ContextInterface $ctx = null): ListTaskQueuePartitionsResponse;

    /**
     * Creates a new schedule.
     *
     * @throws ServiceClientException
     */
    public function CreateSchedule(CreateScheduleRequest $arg, ?ContextInterface $ctx = null): CreateScheduleResponse;

    /**
     * Returns the schedule description and current state of an existing schedule.
     *
     * @throws ServiceClientException
     */
    public function DescribeSchedule(DescribeScheduleRequest $arg, ?ContextInterface $ctx = null): DescribeScheduleResponse;

    /**
     * Changes the configuration or state of an existing schedule.
     *
     * @throws ServiceClientException
     */
    public function UpdateSchedule(UpdateScheduleRequest $arg, ?ContextInterface $ctx = null): UpdateScheduleResponse;

    /**
     * Makes a specific change to a schedule or triggers an immediate action.
     *
     * @throws ServiceClientException
     */
    public function PatchSchedule(PatchScheduleRequest $arg, ?ContextInterface $ctx = null): PatchScheduleResponse;

    /**
     * Lists matching times within a range.
     *
     * @throws ServiceClientException
     */
    public function ListScheduleMatchingTimes(ListScheduleMatchingTimesRequest $arg, ?ContextInterface $ctx = null): ListScheduleMatchingTimesResponse;

    /**
     * Deletes a schedule, removing it from the system.
     *
     * @throws ServiceClientException
     */
    public function DeleteSchedule(DeleteScheduleRequest $arg, ?ContextInterface $ctx = null): DeleteScheduleResponse;

    /**
     * List all schedules in a namespace.
     *
     * @throws ServiceClientException
     */
    public function ListSchedules(ListSchedulesRequest $arg, ?ContextInterface $ctx = null): ListSchedulesResponse;

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
    public function UpdateWorkerBuildIdCompatibility(UpdateWorkerBuildIdCompatibilityRequest $arg, ?ContextInterface $ctx = null): UpdateWorkerBuildIdCompatibilityResponse;

    /**
     * Deprecated. Use `GetWorkerVersioningRules`.
     * Fetches the worker build id versioning sets for a task queue.
     *
     * @throws ServiceClientException
     */
    public function GetWorkerBuildIdCompatibility(GetWorkerBuildIdCompatibilityRequest $arg, ?ContextInterface $ctx = null): GetWorkerBuildIdCompatibilityResponse;

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
    public function UpdateWorkerVersioningRules(UpdateWorkerVersioningRulesRequest $arg, ?ContextInterface $ctx = null): UpdateWorkerVersioningRulesResponse;

    /**
     * Fetches the Build ID assignment and redirect rules for a Task Queue.
     * WARNING: Worker Versioning is not yet stable and the API and behavior may change
     * incompatibly.
     *
     * @throws ServiceClientException
     */
    public function GetWorkerVersioningRules(GetWorkerVersioningRulesRequest $arg, ?ContextInterface $ctx = null): GetWorkerVersioningRulesResponse;

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
    public function GetWorkerTaskReachability(GetWorkerTaskReachabilityRequest $arg, ?ContextInterface $ctx = null): GetWorkerTaskReachabilityResponse;

    /**
     * Describes a worker deployment.
     * Deprecated. Replaced with `DescribeWorkerDeploymentVersion`.
     *
     * @throws ServiceClientException
     */
    public function DescribeDeployment(DescribeDeploymentRequest $arg, ?ContextInterface $ctx = null): DescribeDeploymentResponse;

    /**
     * Describes a worker deployment version.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorkerDeploymentVersion(DescribeWorkerDeploymentVersionRequest $arg, ?ContextInterface $ctx = null): DescribeWorkerDeploymentVersionResponse;

    /**
     * Lists worker deployments in the namespace. Optionally can filter based on
     * deployment series
     * name.
     * Deprecated. Replaced with `ListWorkerDeployments`.
     *
     * @throws ServiceClientException
     */
    public function ListDeployments(ListDeploymentsRequest $arg, ?ContextInterface $ctx = null): ListDeploymentsResponse;

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
     * Deprecated. Replaced with `DrainageInfo` returned by
     * `DescribeWorkerDeploymentVersion`.
     *
     * @throws ServiceClientException
     */
    public function GetDeploymentReachability(GetDeploymentReachabilityRequest $arg, ?ContextInterface $ctx = null): GetDeploymentReachabilityResponse;

    /**
     * Returns the current deployment (and its info) for a given deployment series.
     * Deprecated. Replaced by `current_version` returned by
     * `DescribeWorkerDeployment`.
     *
     * @throws ServiceClientException
     */
    public function GetCurrentDeployment(GetCurrentDeploymentRequest $arg, ?ContextInterface $ctx = null): GetCurrentDeploymentResponse;

    /**
     * Sets a deployment as the current deployment for its deployment series. Can
     * optionally update
     * the metadata of the deployment as well.
     * Deprecated. Replaced by `SetWorkerDeploymentCurrentVersion`.
     *
     * @throws ServiceClientException
     */
    public function SetCurrentDeployment(SetCurrentDeploymentRequest $arg, ?ContextInterface $ctx = null): SetCurrentDeploymentResponse;

    /**
     * Set/unset the Current Version of a Worker Deployment. Automatically unsets the
     * Ramping
     * Version if it is the Version being set as Current.
     *
     * @throws ServiceClientException
     */
    public function SetWorkerDeploymentCurrentVersion(SetWorkerDeploymentCurrentVersionRequest $arg, ?ContextInterface $ctx = null): SetWorkerDeploymentCurrentVersionResponse;

    /**
     * Describes a Worker Deployment.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorkerDeployment(DescribeWorkerDeploymentRequest $arg, ?ContextInterface $ctx = null): DescribeWorkerDeploymentResponse;

    /**
     * Deletes records of (an old) Deployment. A deployment can only be deleted if
     * it has no Version in it.
     *
     * @throws ServiceClientException
     */
    public function DeleteWorkerDeployment(DeleteWorkerDeploymentRequest $arg, ?ContextInterface $ctx = null): DeleteWorkerDeploymentResponse;

    /**
     * Used for manual deletion of Versions. User can delete a Version only when all
     * the
     * following conditions are met:
     * - It is not the Current or Ramping Version of its Deployment.
     * - It has no active pollers (none of the task queues in the Version have pollers)
     * - It is not draining (see WorkerDeploymentVersionInfo.drainage_info). This
     * condition
     * can be skipped by passing `skip-drainage=true`.
     *
     * @throws ServiceClientException
     */
    public function DeleteWorkerDeploymentVersion(DeleteWorkerDeploymentVersionRequest $arg, ?ContextInterface $ctx = null): DeleteWorkerDeploymentVersionResponse;

    /**
     * Set/unset the Ramping Version of a Worker Deployment and its ramp percentage.
     * Can be used for
     * gradual ramp to unversioned workers too.
     *
     * @throws ServiceClientException
     */
    public function SetWorkerDeploymentRampingVersion(SetWorkerDeploymentRampingVersionRequest $arg, ?ContextInterface $ctx = null): SetWorkerDeploymentRampingVersionResponse;

    /**
     * Lists all Worker Deployments that are tracked in the Namespace.
     *
     * @throws ServiceClientException
     */
    public function ListWorkerDeployments(ListWorkerDeploymentsRequest $arg, ?ContextInterface $ctx = null): ListWorkerDeploymentsResponse;

    /**
     * Updates the user-given metadata attached to a Worker Deployment Version.
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkerDeploymentVersionMetadata(UpdateWorkerDeploymentVersionMetadataRequest $arg, ?ContextInterface $ctx = null): UpdateWorkerDeploymentVersionMetadataResponse;

    /**
     * Set/unset the ManagerIdentity of a Worker Deployment.
     *
     * @throws ServiceClientException
     */
    public function SetWorkerDeploymentManager(SetWorkerDeploymentManagerRequest $arg, ?ContextInterface $ctx = null): SetWorkerDeploymentManagerResponse;

    /**
     * Invokes the specified Update function on user Workflow code.
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkflowExecution(UpdateWorkflowExecutionRequest $arg, ?ContextInterface $ctx = null): UpdateWorkflowExecutionResponse;

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
    public function PollWorkflowExecutionUpdate(PollWorkflowExecutionUpdateRequest $arg, ?ContextInterface $ctx = null): PollWorkflowExecutionUpdateResponse;

    /**
     * StartBatchOperation starts a new batch operation
     *
     * @throws ServiceClientException
     */
    public function StartBatchOperation(StartBatchOperationRequest $arg, ?ContextInterface $ctx = null): StartBatchOperationResponse;

    /**
     * StopBatchOperation stops a batch operation
     *
     * @throws ServiceClientException
     */
    public function StopBatchOperation(StopBatchOperationRequest $arg, ?ContextInterface $ctx = null): StopBatchOperationResponse;

    /**
     * DescribeBatchOperation returns the information about a batch operation
     *
     * @throws ServiceClientException
     */
    public function DescribeBatchOperation(DescribeBatchOperationRequest $arg, ?ContextInterface $ctx = null): DescribeBatchOperationResponse;

    /**
     * ListBatchOperations returns a list of batch operations
     *
     * @throws ServiceClientException
     */
    public function ListBatchOperations(ListBatchOperationsRequest $arg, ?ContextInterface $ctx = null): ListBatchOperationsResponse;

    /**
     * PollNexusTaskQueue is a long poll call used by workers to receive Nexus tasks.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function PollNexusTaskQueue(PollNexusTaskQueueRequest $arg, ?ContextInterface $ctx = null): PollNexusTaskQueueResponse;

    /**
     * RespondNexusTaskCompleted is called by workers to respond to Nexus tasks
     * received via PollNexusTaskQueue.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function RespondNexusTaskCompleted(RespondNexusTaskCompletedRequest $arg, ?ContextInterface $ctx = null): RespondNexusTaskCompletedResponse;

    /**
     * RespondNexusTaskFailed is called by workers to fail Nexus tasks received via
     * PollNexusTaskQueue.
     * (-- api-linter: core::0127::http-annotation=disabled
     * aip.dev/not-precedent: We do not expose worker API to HTTP. --)
     *
     * @throws ServiceClientException
     */
    public function RespondNexusTaskFailed(RespondNexusTaskFailedRequest $arg, ?ContextInterface $ctx = null): RespondNexusTaskFailedResponse;

    /**
     * UpdateActivityOptions is called by the client to update the options of an
     * activity by its ID or type.
     * If there are multiple pending activities of the provided type - all of them will
     * be updated.
     *
     * @throws ServiceClientException
     */
    public function UpdateActivityOptions(UpdateActivityOptionsRequest $arg, ?ContextInterface $ctx = null): UpdateActivityOptionsResponse;

    /**
     * UpdateWorkflowExecutionOptions partially updates the WorkflowExecutionOptions of
     * an existing workflow execution.
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkflowExecutionOptions(UpdateWorkflowExecutionOptionsRequest $arg, ?ContextInterface $ctx = null): UpdateWorkflowExecutionOptionsResponse;

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
    public function PauseActivity(PauseActivityRequest $arg, ?ContextInterface $ctx = null): PauseActivityResponse;

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
    public function UnpauseActivity(UnpauseActivityRequest $arg, ?ContextInterface $ctx = null): UnpauseActivityResponse;

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
    public function ResetActivity(ResetActivityRequest $arg, ?ContextInterface $ctx = null): ResetActivityResponse;

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
    public function CreateWorkflowRule(CreateWorkflowRuleRequest $arg, ?ContextInterface $ctx = null): CreateWorkflowRuleResponse;

    /**
     * DescribeWorkflowRule return the rule specification for existing rule id.
     * If there is no rule with such id - NOT FOUND error will be returned.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorkflowRule(DescribeWorkflowRuleRequest $arg, ?ContextInterface $ctx = null): DescribeWorkflowRuleResponse;

    /**
     * Delete rule by rule id
     *
     * @throws ServiceClientException
     */
    public function DeleteWorkflowRule(DeleteWorkflowRuleRequest $arg, ?ContextInterface $ctx = null): DeleteWorkflowRuleResponse;

    /**
     * Return all namespace workflow rules
     *
     * @throws ServiceClientException
     */
    public function ListWorkflowRules(ListWorkflowRulesRequest $arg, ?ContextInterface $ctx = null): ListWorkflowRulesResponse;

    /**
     * TriggerWorkflowRule allows to:
     * trigger existing rule for a specific workflow execution;
     * trigger rule for a specific workflow execution without creating a rule;
     * This is useful for one-off operations.
     *
     * @throws ServiceClientException
     */
    public function TriggerWorkflowRule(TriggerWorkflowRuleRequest $arg, ?ContextInterface $ctx = null): TriggerWorkflowRuleResponse;

    /**
     * WorkerHeartbeat receive heartbeat request from the worker.
     *
     * @throws ServiceClientException
     */
    public function RecordWorkerHeartbeat(RecordWorkerHeartbeatRequest $arg, ?ContextInterface $ctx = null): RecordWorkerHeartbeatResponse;

    /**
     * ListWorkers is a visibility API to list worker status information in a specific
     * namespace.
     *
     * @throws ServiceClientException
     */
    public function ListWorkers(ListWorkersRequest $arg, ?ContextInterface $ctx = null): ListWorkersResponse;

    /**
     * Updates task queue configuration.
     * For the overall queue rate limit: the rate limit set by this api overrides the
     * worker-set rate limit,
     * which uncouples the rate limit from the worker lifecycle.
     * If the overall queue rate limit is unset, the worker-set rate limit takes
     * effect.
     *
     * @throws ServiceClientException
     */
    public function UpdateTaskQueueConfig(UpdateTaskQueueConfigRequest $arg, ?ContextInterface $ctx = null): UpdateTaskQueueConfigResponse;

    /**
     * FetchWorkerConfig returns the worker configuration for a specific worker.
     *
     * @throws ServiceClientException
     */
    public function FetchWorkerConfig(FetchWorkerConfigRequest $arg, ?ContextInterface $ctx = null): FetchWorkerConfigResponse;

    /**
     * UpdateWorkerConfig updates the worker configuration of one or more workers.
     * Can be used to partially update the worker configuration.
     * Can be used to update the configuration of multiple workers.
     *
     * @throws ServiceClientException
     */
    public function UpdateWorkerConfig(UpdateWorkerConfigRequest $arg, ?ContextInterface $ctx = null): UpdateWorkerConfigResponse;

    /**
     * DescribeWorker returns information about the specified worker.
     *
     * @throws ServiceClientException
     */
    public function DescribeWorker(DescribeWorkerRequest $arg, ?ContextInterface $ctx = null): DescribeWorkerResponse;
}
