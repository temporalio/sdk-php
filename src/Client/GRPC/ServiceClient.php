<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

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
     * all resources.  Namespace is a top level
     * entity within Temporal, used as a container for all resources like workflow
     * executions, task queues, etc.  Namespace
     * acts as a sandbox and provides isolation for all resources within the namespace.
     *  All resources belongs to exactly one
     * namespace.
     *
     * @param V1\RegisterNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RegisterNamespaceResponse
     * @throws ServiceClientException
     */
    public function RegisterNamespace(
        V1\RegisterNamespaceRequest $arg,
        ContextInterface $ctx = null
    ): V1\RegisterNamespaceResponse {
        return $this->invoke('RegisterNamespace', $arg, $ctx);
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
    public function DescribeNamespace(
        V1\DescribeNamespaceRequest $arg,
        ContextInterface $ctx = null
    ): V1\DescribeNamespaceResponse {
        return $this->invoke('DescribeNamespace', $arg, $ctx);
    }

    /**
     * ListNamespaces returns the information and configuration for all namespaces.
     *
     * @param V1\ListNamespacesRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListNamespacesResponse
     * @throws ServiceClientException
     */
    public function ListNamespaces(
        V1\ListNamespacesRequest $arg,
        ContextInterface $ctx = null
    ): V1\ListNamespacesResponse {
        return $this->invoke('ListNamespaces', $arg, $ctx);
    }

    /**
     * (-- api-linter: core::0134::method-signature=disabled
     * aip.dev/not-precedent: UpdateNamespace RPC doesn't follow Google API format. --)
     * (-- api-linter: core::0134::response-message-name=disabled
     * aip.dev/not-precedent: UpdateNamespace RPC doesn't follow Google API format. --)
     * UpdateNamespace is used to update the information and configuration for a
     * registered namespace.
     *
     * @param V1\UpdateNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\UpdateNamespaceResponse
     * @throws ServiceClientException
     */
    public function UpdateNamespace(
        V1\UpdateNamespaceRequest $arg,
        ContextInterface $ctx = null
    ): V1\UpdateNamespaceResponse {
        return $this->invoke('UpdateNamespace', $arg, $ctx);
    }

    /**
     * DeprecateNamespace is used to update state of a registered namespace to
     * DEPRECATED.  Once the namespace is deprecated
     * it cannot be used to start new workflow executions.  Existing workflow
     * executions will continue to run on
     * deprecated namespaces.
     *
     * @param V1\DeprecateNamespaceRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DeprecateNamespaceResponse
     * @throws ServiceClientException
     */
    public function DeprecateNamespace(
        V1\DeprecateNamespaceRequest $arg,
        ContextInterface $ctx = null
    ): V1\DeprecateNamespaceResponse {
        return $this->invoke('DeprecateNamespace', $arg, $ctx);
    }

    /**
     * StartWorkflowExecution starts a new long running workflow instance.  It will
     * create the instance with
     * 'WorkflowExecutionStarted' event in history and also schedule the first
     * WorkflowTask for the worker to make the
     * first command for this instance.  It will return
     * 'WorkflowExecutionAlreadyStartedFailure', if an instance already
     * exists with same workflowId.
     *
     * @param V1\StartWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\StartWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function StartWorkflowExecution(
        V1\StartWorkflowExecutionRequest $arg,
        ContextInterface $ctx = null
    ): V1\StartWorkflowExecutionResponse {
        return $this->invoke('StartWorkflowExecution', $arg, $ctx);
    }

    /**
     * GetWorkflowExecutionHistory returns the history of specified workflow execution.
     *  It fails with 'NotFoundFailure' if specified workflow
     * execution in unknown to the service.
     *
     * @param V1\GetWorkflowExecutionHistoryRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetWorkflowExecutionHistoryResponse
     * @throws ServiceClientException
     */
    public function GetWorkflowExecutionHistory(
        V1\GetWorkflowExecutionHistoryRequest $arg,
        ContextInterface $ctx = null
    ): V1\GetWorkflowExecutionHistoryResponse {
        return $this->invoke('GetWorkflowExecutionHistory', $arg, $ctx);
    }

    /**
     * PollWorkflowTaskQueue is called by application worker to process WorkflowTask
     * from a specific task queue.  A
     * WorkflowTask is dispatched to callers for active workflow executions, with
     * pending workflow tasks.
     * Application is then expected to call 'RespondWorkflowTaskCompleted' API when it
     * is done processing the WorkflowTask.
     * It will also create a 'WorkflowTaskStarted' event in the history for that
     * session before handing off WorkflowTask to
     * application worker.
     *
     * @param V1\PollWorkflowTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PollWorkflowTaskQueueResponse
     * @throws ServiceClientException
     */
    public function PollWorkflowTaskQueue(
        V1\PollWorkflowTaskQueueRequest $arg,
        ContextInterface $ctx = null
    ): V1\PollWorkflowTaskQueueResponse {
        return $this->invoke('PollWorkflowTaskQueue', $arg, $ctx);
    }

    /**
     * RespondWorkflowTaskCompleted is called by application worker to complete a
     * WorkflowTask handed as a result of
     * 'PollWorkflowTaskQueue' API call.  Completing a WorkflowTask will result in new
     * events for the workflow execution and
     * potentially new ActivityTask being created for corresponding commands.  It will
     * also create a WorkflowTaskCompleted
     * event in the history for that session.  Use the 'taskToken' provided as response
     * of PollWorkflowTaskQueue API call
     * for completing the WorkflowTask.
     * The response could contain a new workflow task if there is one or if the request
     * asking for one.
     *
     * @param V1\RespondWorkflowTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondWorkflowTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondWorkflowTaskCompleted(
        V1\RespondWorkflowTaskCompletedRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondWorkflowTaskCompletedResponse {
        return $this->invoke('RespondWorkflowTaskCompleted', $arg, $ctx);
    }

    /**
     * RespondWorkflowTaskFailed is called by application worker to indicate failure.
     * This results in
     * WorkflowTaskFailedEvent written to the history and a new WorkflowTask created.
     * This API can be used by client to
     * either clear sticky task queue or report any panics during WorkflowTask
     * processing.  Temporal will only append first
     * WorkflowTaskFailed event to the history of workflow execution for consecutive
     * failures.
     *
     * @param V1\RespondWorkflowTaskFailedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondWorkflowTaskFailedResponse
     * @throws ServiceClientException
     */
    public function RespondWorkflowTaskFailed(
        V1\RespondWorkflowTaskFailedRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondWorkflowTaskFailedResponse {
        return $this->invoke('RespondWorkflowTaskFailed', $arg, $ctx);
    }

    /**
     * PollActivityTaskQueue is called by application worker to process ActivityTask
     * from a specific task queue.  ActivityTask
     * is dispatched to callers whenever a ScheduleTask command is made for a workflow
     * execution.
     * Application is expected to call 'RespondActivityTaskCompleted' or
     * 'RespondActivityTaskFailed' once it is done
     * processing the task.
     * Application also needs to call 'RecordActivityTaskHeartbeat' API within
     * 'heartbeatTimeoutSeconds' interval to
     * prevent the task from getting timed out.  An event 'ActivityTaskStarted' event
     * is also written to workflow execution
     * history before the ActivityTask is dispatched to application worker.
     *
     * @param V1\PollActivityTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\PollActivityTaskQueueResponse
     * @throws ServiceClientException
     */
    public function PollActivityTaskQueue(
        V1\PollActivityTaskQueueRequest $arg,
        ContextInterface $ctx = null
    ): V1\PollActivityTaskQueueResponse {
        return $this->invoke('PollActivityTaskQueue', $arg, $ctx);
    }

    /**
     * RecordActivityTaskHeartbeat is called by application worker while it is
     * processing an ActivityTask.  If worker fails
     * to heartbeat within 'heartbeatTimeoutSeconds' interval for the ActivityTask,
     * then it will be marked as timedout and
     * 'ActivityTaskTimedOut' event will be written to the workflow history.  Calling
     * 'RecordActivityTaskHeartbeat' will
     * fail with 'NotFoundFailure' in such situations.  Use the 'taskToken' provided as
     * response of
     * PollActivityTaskQueue API call for heart beating.
     *
     * @param V1\RecordActivityTaskHeartbeatRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RecordActivityTaskHeartbeatResponse
     * @throws ServiceClientException
     */
    public function RecordActivityTaskHeartbeat(
        V1\RecordActivityTaskHeartbeatRequest $arg,
        ContextInterface $ctx = null
    ): V1\RecordActivityTaskHeartbeatResponse {
        return $this->invoke('RecordActivityTaskHeartbeat', $arg, $ctx);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     * RecordActivityTaskHeartbeatById is called by application worker while it is
     * processing an ActivityTask.  If worker fails
     * to heartbeat within 'heartbeatTimeoutSeconds' interval for the ActivityTask,
     * then it will be marked as timed out and
     * 'ActivityTaskTimedOut' event will be written to the workflow history.  Calling
     * 'RecordActivityTaskHeartbeatById' will
     * fail with 'NotFoundFailure' in such situations.  Instead of using 'taskToken'
     * like in RecordActivityTaskHeartbeat,
     * use Namespace, WorkflowId and ActivityId
     *
     * @param V1\RecordActivityTaskHeartbeatByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RecordActivityTaskHeartbeatByIdResponse
     * @throws ServiceClientException
     */
    public function RecordActivityTaskHeartbeatById(
        V1\RecordActivityTaskHeartbeatByIdRequest $arg,
        ContextInterface $ctx = null
    ): V1\RecordActivityTaskHeartbeatByIdResponse {
        return $this->invoke('RecordActivityTaskHeartbeatById', $arg, $ctx);
    }

    /**
     * RespondActivityTaskCompleted is called by application worker when it is done
     * processing an ActivityTask.  It will
     * result in a new 'ActivityTaskCompleted' event being written to the workflow
     * history and a new WorkflowTask
     * created for the workflow so new commands could be made.  Use the 'taskToken'
     * provided as response of
     * PollActivityTaskQueue API call for completion. It fails with 'NotFoundFailure'
     * if the taskToken is not valid
     * anymore due to activity timeout.
     *
     * @param V1\RespondActivityTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCompleted(
        V1\RespondActivityTaskCompletedRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondActivityTaskCompletedResponse {
        return $this->invoke('RespondActivityTaskCompleted', $arg, $ctx);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     * RespondActivityTaskCompletedById is called by application worker when it is done
     * processing an ActivityTask.
     * It will result in a new 'ActivityTaskCompleted' event being written to the
     * workflow history and a new WorkflowTask
     * created for the workflow so new commands could be made.  Similar to
     * RespondActivityTaskCompleted but use Namespace,
     * WorkflowId and ActivityId instead of 'taskToken' for completion. It fails with
     * 'NotFoundFailure'
     * if the these Ids are not valid anymore due to activity timeout.
     *
     * @param V1\RespondActivityTaskCompletedByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCompletedByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCompletedById(
        V1\RespondActivityTaskCompletedByIdRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondActivityTaskCompletedByIdResponse {
        return $this->invoke('RespondActivityTaskCompletedById', $arg, $ctx);
    }

    /**
     * RespondActivityTaskFailed is called by application worker when it is done
     * processing an ActivityTask.  It will
     * result in a new 'ActivityTaskFailed' event being written to the workflow history
     * and a new WorkflowTask
     * created for the workflow instance so new commands could be made.  Use the
     * 'taskToken' provided as response of
     * PollActivityTaskQueue API call for completion. It fails with 'NotFoundFailure'
     * if the taskToken is not valid
     * anymore due to activity timeout.
     *
     * @param V1\RespondActivityTaskFailedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskFailedResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskFailed(
        V1\RespondActivityTaskFailedRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondActivityTaskFailedResponse {
        return $this->invoke('RespondActivityTaskFailed', $arg, $ctx);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     * RespondActivityTaskFailedById is called by application worker when it is done
     * processing an ActivityTask.
     * It will result in a new 'ActivityTaskFailed' event being written to the workflow
     * history and a new WorkflowTask
     * created for the workflow instance so new commands could be made.  Similar to
     * RespondActivityTaskFailed but use
     * Namespace, WorkflowId and ActivityId instead of 'taskToken' for completion. It
     * fails with 'NotFoundFailure'
     * if the these Ids are not valid anymore due to activity timeout.
     *
     * @param V1\RespondActivityTaskFailedByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskFailedByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskFailedById(
        V1\RespondActivityTaskFailedByIdRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondActivityTaskFailedByIdResponse {
        return $this->invoke('RespondActivityTaskFailedById', $arg, $ctx);
    }

    /**
     * RespondActivityTaskCanceled is called by application worker when it is
     * successfully canceled an ActivityTask.  It will
     * result in a new 'ActivityTaskCanceled' event being written to the workflow
     * history and a new WorkflowTask
     * created for the workflow instance so new commands could be made.  Use the
     * 'taskToken' provided as response of
     * PollActivityTaskQueue API call for completion. It fails with 'NotFoundFailure'
     * if the taskToken is not valid
     * anymore due to activity timeout.
     *
     * @param V1\RespondActivityTaskCanceledRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCanceledResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCanceled(
        V1\RespondActivityTaskCanceledRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondActivityTaskCanceledResponse {
        return $this->invoke('RespondActivityTaskCanceled', $arg, $ctx);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "By" is used to indicate request type. --)
     * RespondActivityTaskCanceledById is called by application worker when it is
     * successfully canceled an ActivityTask.
     * It will result in a new 'ActivityTaskCanceled' event being written to the
     * workflow history and a new WorkflowTask
     * created for the workflow instance so new commands could be made.  Similar to
     * RespondActivityTaskCanceled but use
     * Namespace, WorkflowId and ActivityId instead of 'taskToken' for completion. It
     * fails with 'NotFoundFailure'
     * if the these Ids are not valid anymore due to activity timeout.
     *
     * @param V1\RespondActivityTaskCanceledByIdRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondActivityTaskCanceledByIdResponse
     * @throws ServiceClientException
     */
    public function RespondActivityTaskCanceledById(
        V1\RespondActivityTaskCanceledByIdRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondActivityTaskCanceledByIdResponse {
        return $this->invoke('RespondActivityTaskCanceledById', $arg, $ctx);
    }

    /**
     * RequestCancelWorkflowExecution is called by application worker when it wants to
     * request cancellation of a workflow instance.
     * It will result in a new 'WorkflowExecutionCancelRequested' event being written
     * to the workflow history and a new WorkflowTask
     * created for the workflow instance so new commands could be made. It fails with
     * 'NotFoundFailure' if the workflow is not valid
     * anymore due to completion or doesn't exist.
     *
     * @param V1\RequestCancelWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RequestCancelWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function RequestCancelWorkflowExecution(
        V1\RequestCancelWorkflowExecutionRequest $arg,
        ContextInterface $ctx = null
    ): V1\RequestCancelWorkflowExecutionResponse {
        return $this->invoke('RequestCancelWorkflowExecution', $arg, $ctx);
    }

    /**
     * SignalWorkflowExecution is used to send a signal event to running workflow
     * execution.  This results in
     * WorkflowExecutionSignaled event recorded in the history and a workflow task
     * being created for the execution.
     *
     * @param V1\SignalWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\SignalWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function SignalWorkflowExecution(
        V1\SignalWorkflowExecutionRequest $arg,
        ContextInterface $ctx = null
    ): V1\SignalWorkflowExecutionResponse {
        return $this->invoke('SignalWorkflowExecution', $arg, $ctx);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     * aip.dev/not-precedent: "With" is used to indicate combined operation. --)
     * SignalWithStartWorkflowExecution is used to ensure sending signal to a workflow.
     * If the workflow is running, this results in WorkflowExecutionSignaled event
     * being recorded in the history
     * and a workflow task being created for the execution.
     * If the workflow is not running or not found, this results in
     * WorkflowExecutionStarted and WorkflowExecutionSignaled
     * events being recorded in history, and a workflow task being created for the
     * execution
     *
     * @param V1\SignalWithStartWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\SignalWithStartWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function SignalWithStartWorkflowExecution(
        V1\SignalWithStartWorkflowExecutionRequest $arg,
        ContextInterface $ctx = null
    ): V1\SignalWithStartWorkflowExecutionResponse {
        return $this->invoke('SignalWithStartWorkflowExecution', $arg, $ctx);
    }

    /**
     * ResetWorkflowExecution reset an existing workflow execution to
     * WorkflowTaskCompleted event(exclusive).
     * And it will immediately terminating the current execution instance.
     *
     * @param V1\ResetWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ResetWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function ResetWorkflowExecution(
        V1\ResetWorkflowExecutionRequest $arg,
        ContextInterface $ctx = null
    ): V1\ResetWorkflowExecutionResponse {
        return $this->invoke('ResetWorkflowExecution', $arg, $ctx);
    }

    /**
     * TerminateWorkflowExecution terminates an existing workflow execution by
     * recording WorkflowExecutionTerminated event
     * in the history and immediately terminating the execution instance.
     *
     * @param V1\TerminateWorkflowExecutionRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\TerminateWorkflowExecutionResponse
     * @throws ServiceClientException
     */
    public function TerminateWorkflowExecution(
        V1\TerminateWorkflowExecutionRequest $arg,
        ContextInterface $ctx = null
    ): V1\TerminateWorkflowExecutionResponse {
        return $this->invoke('TerminateWorkflowExecution', $arg, $ctx);
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
    public function ListOpenWorkflowExecutions(
        V1\ListOpenWorkflowExecutionsRequest $arg,
        ContextInterface $ctx = null
    ): V1\ListOpenWorkflowExecutionsResponse {
        return $this->invoke('ListOpenWorkflowExecutions', $arg, $ctx);
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
    public function ListClosedWorkflowExecutions(
        V1\ListClosedWorkflowExecutionsRequest $arg,
        ContextInterface $ctx = null
    ): V1\ListClosedWorkflowExecutionsResponse {
        return $this->invoke('ListClosedWorkflowExecutions', $arg, $ctx);
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
    public function ListWorkflowExecutions(
        V1\ListWorkflowExecutionsRequest $arg,
        ContextInterface $ctx = null
    ): V1\ListWorkflowExecutionsResponse {
        return $this->invoke('ListWorkflowExecutions', $arg, $ctx);
    }

    /**
     * ListArchivedWorkflowExecutions is a visibility API to list archived workflow
     * executions in a specific namespace.
     *
     * @param V1\ListArchivedWorkflowExecutionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListArchivedWorkflowExecutionsResponse
     * @throws ServiceClientException
     */
    public function ListArchivedWorkflowExecutions(
        V1\ListArchivedWorkflowExecutionsRequest $arg,
        ContextInterface $ctx = null
    ): V1\ListArchivedWorkflowExecutionsResponse {
        return $this->invoke('ListArchivedWorkflowExecutions', $arg, $ctx);
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
    public function ScanWorkflowExecutions(
        V1\ScanWorkflowExecutionsRequest $arg,
        ContextInterface $ctx = null
    ): V1\ScanWorkflowExecutionsResponse {
        return $this->invoke('ScanWorkflowExecutions', $arg, $ctx);
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
    public function CountWorkflowExecutions(
        V1\CountWorkflowExecutionsRequest $arg,
        ContextInterface $ctx = null
    ): V1\CountWorkflowExecutionsResponse {
        return $this->invoke('CountWorkflowExecutions', $arg, $ctx);
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
    public function GetSearchAttributes(
        V1\GetSearchAttributesRequest $arg,
        ContextInterface $ctx = null
    ): V1\GetSearchAttributesResponse {
        return $this->invoke('GetSearchAttributes', $arg, $ctx);
    }

    /**
     * RespondQueryTaskCompleted is called by application worker to complete a
     * QueryTask (which is a WorkflowTask for query)
     * as a result of 'PollWorkflowTaskQueue' API call. Completing a QueryTask will
     * unblock the client call to 'QueryWorkflow'
     * API and return the query result to client as a response to 'QueryWorkflow' API
     * call.
     *
     * @param V1\RespondQueryTaskCompletedRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\RespondQueryTaskCompletedResponse
     * @throws ServiceClientException
     */
    public function RespondQueryTaskCompleted(
        V1\RespondQueryTaskCompletedRequest $arg,
        ContextInterface $ctx = null
    ): V1\RespondQueryTaskCompletedResponse {
        return $this->invoke('RespondQueryTaskCompleted', $arg, $ctx);
    }

    /**
     * ResetStickyTaskQueue resets the sticky task queue related information in mutable
     * state of a given workflow.
     * Things cleared are:
     * 1. StickyTaskQueue
     * 2. StickyScheduleToStartTimeout
     *
     * @param V1\ResetStickyTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ResetStickyTaskQueueResponse
     * @throws ServiceClientException
     */
    public function ResetStickyTaskQueue(
        V1\ResetStickyTaskQueueRequest $arg,
        ContextInterface $ctx = null
    ): V1\ResetStickyTaskQueueResponse {
        return $this->invoke('ResetStickyTaskQueue', $arg, $ctx);
    }

    /**
     * QueryWorkflow returns query result for a specified workflow execution
     *
     * @param V1\QueryWorkflowRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\QueryWorkflowResponse
     * @throws ServiceClientException
     */
    public function QueryWorkflow(V1\QueryWorkflowRequest $arg, ContextInterface $ctx = null): V1\QueryWorkflowResponse
    {
        return $this->invoke('QueryWorkflow', $arg, $ctx);
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
    public function DescribeWorkflowExecution(
        V1\DescribeWorkflowExecutionRequest $arg,
        ContextInterface $ctx = null
    ): V1\DescribeWorkflowExecutionResponse {
        return $this->invoke('DescribeWorkflowExecution', $arg, $ctx);
    }

    /**
     * DescribeTaskQueue returns information about the target task queue, right now
     * this API returns the
     * pollers which polled this task queue in last few minutes.
     *
     * @param V1\DescribeTaskQueueRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\DescribeTaskQueueResponse
     * @throws ServiceClientException
     */
    public function DescribeTaskQueue(
        V1\DescribeTaskQueueRequest $arg,
        ContextInterface $ctx = null
    ): V1\DescribeTaskQueueResponse {
        return $this->invoke('DescribeTaskQueue', $arg, $ctx);
    }

    /**
     * GetClusterInfo returns information about temporal cluster
     *
     * @param V1\GetClusterInfoRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\GetClusterInfoResponse
     * @throws ServiceClientException
     */
    public function GetClusterInfo(
        V1\GetClusterInfoRequest $arg,
        ContextInterface $ctx = null
    ): V1\GetClusterInfoResponse {
        return $this->invoke('GetClusterInfo', $arg, $ctx);
    }

    /**
     * @param V1\ListTaskQueuePartitionsRequest $arg
     * @param ContextInterface|null $ctx
     * @return V1\ListTaskQueuePartitionsResponse
     * @throws ServiceClientException
     */
    public function ListTaskQueuePartitions(
        V1\ListTaskQueuePartitionsRequest $arg,
        ContextInterface $ctx = null
    ): V1\ListTaskQueuePartitionsResponse {
        return $this->invoke('ListTaskQueuePartitions', $arg, $ctx);
    }
}
