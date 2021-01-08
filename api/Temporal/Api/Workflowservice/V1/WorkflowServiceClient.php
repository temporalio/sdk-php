<?php
// GENERATED CODE -- DO NOT EDIT!

// Original file comments:
// The MIT License
// 
// Copyright (c) 2020 Temporal Technologies Inc.  All rights reserved.
// 
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
// 
// The above copyright notice and this permission notice shall be included in
// all copies or substantial portions of the Software.
// 
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
// THE SOFTWARE.
//
namespace Temporal\Api\Workflowservice\V1;

/**
 * WorkflowService API is exposed to provide support for long running applications.  Application is expected to call
 * StartWorkflowExecution to create an instance for each instance of long running workflow.  Such applications are expected
 * to have a worker which regularly polls for WorkflowTask and ActivityTask from the WorkflowService.  For each
 * WorkflowTask, application is expected to process the history of events for that session and respond back with next
 * commands.  For each ActivityTask, application is expected to execute the actual logic for that task and respond back
 * with completion or failure.  Worker is expected to regularly heartbeat while activity task is running.
 */
class WorkflowServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * RegisterNamespace creates a new namespace which can be used as a container for all resources.  Namespace is a top level
     * entity within Temporal, used as a container for all resources like workflow executions, task queues, etc.  Namespace
     * acts as a sandbox and provides isolation for all resources within the namespace.  All resources belongs to exactly one
     * namespace.
     * @param \Temporal\Api\Workflowservice\V1\RegisterNamespaceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RegisterNamespace(\Temporal\Api\Workflowservice\V1\RegisterNamespaceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RegisterNamespace',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RegisterNamespaceResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * DescribeNamespace returns the information and configuration for a registered namespace.
     * @param \Temporal\Api\Workflowservice\V1\DescribeNamespaceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeNamespace(\Temporal\Api\Workflowservice\V1\DescribeNamespaceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/DescribeNamespace',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\DescribeNamespaceResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ListNamespaces returns the information and configuration for all namespaces.
     * @param \Temporal\Api\Workflowservice\V1\ListNamespacesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListNamespaces(\Temporal\Api\Workflowservice\V1\ListNamespacesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ListNamespaces',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ListNamespacesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * (-- api-linter: core::0134::method-signature=disabled
     *     aip.dev/not-precedent: UpdateNamespace RPC doesn't follow Google API format. --)
     * (-- api-linter: core::0134::response-message-name=disabled
     *     aip.dev/not-precedent: UpdateNamespace RPC doesn't follow Google API format. --)
     * UpdateNamespace is used to update the information and configuration for a registered namespace.
     * @param \Temporal\Api\Workflowservice\V1\UpdateNamespaceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function UpdateNamespace(\Temporal\Api\Workflowservice\V1\UpdateNamespaceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/UpdateNamespace',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\UpdateNamespaceResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * DeprecateNamespace is used to update state of a registered namespace to DEPRECATED.  Once the namespace is deprecated
     * it cannot be used to start new workflow executions.  Existing workflow executions will continue to run on
     * deprecated namespaces.
     * @param \Temporal\Api\Workflowservice\V1\DeprecateNamespaceRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DeprecateNamespace(\Temporal\Api\Workflowservice\V1\DeprecateNamespaceRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/DeprecateNamespace',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\DeprecateNamespaceResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * StartWorkflowExecution starts a new long running workflow instance.  It will create the instance with
     * 'WorkflowExecutionStarted' event in history and also schedule the first WorkflowTask for the worker to make the
     * first command for this instance.  It will return 'WorkflowExecutionAlreadyStartedFailure', if an instance already
     * exists with same workflowId.
     * @param \Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function StartWorkflowExecution(\Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/StartWorkflowExecution',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\StartWorkflowExecutionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * GetWorkflowExecutionHistory returns the history of specified workflow execution.  It fails with 'NotFoundFailure' if specified workflow
     * execution in unknown to the service.
     * @param \Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetWorkflowExecutionHistory(\Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/GetWorkflowExecutionHistory',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\GetWorkflowExecutionHistoryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * PollWorkflowTaskQueue is called by application worker to process WorkflowTask from a specific task queue.  A
     * WorkflowTask is dispatched to callers for active workflow executions, with pending workflow tasks.
     * Application is then expected to call 'RespondWorkflowTaskCompleted' API when it is done processing the WorkflowTask.
     * It will also create a 'WorkflowTaskStarted' event in the history for that session before handing off WorkflowTask to
     * application worker.
     * @param \Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function PollWorkflowTaskQueue(\Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/PollWorkflowTaskQueue',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\PollWorkflowTaskQueueResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RespondWorkflowTaskCompleted is called by application worker to complete a WorkflowTask handed as a result of
     * 'PollWorkflowTaskQueue' API call.  Completing a WorkflowTask will result in new events for the workflow execution and
     * potentially new ActivityTask being created for corresponding commands.  It will also create a WorkflowTaskCompleted
     * event in the history for that session.  Use the 'taskToken' provided as response of PollWorkflowTaskQueue API call
     * for completing the WorkflowTask.
     * The response could contain a new workflow task if there is one or if the request asking for one.
     * @param \Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondWorkflowTaskCompleted(\Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondWorkflowTaskCompleted',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondWorkflowTaskCompletedResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RespondWorkflowTaskFailed is called by application worker to indicate failure.  This results in
     * WorkflowTaskFailedEvent written to the history and a new WorkflowTask created.  This API can be used by client to
     * either clear sticky task queue or report any panics during WorkflowTask processing.  Temporal will only append first
     * WorkflowTaskFailed event to the history of workflow execution for consecutive failures.
     * @param \Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondWorkflowTaskFailed(\Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondWorkflowTaskFailed',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondWorkflowTaskFailedResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * PollActivityTaskQueue is called by application worker to process ActivityTask from a specific task queue.  ActivityTask
     * is dispatched to callers whenever a ScheduleTask command is made for a workflow execution.
     * Application is expected to call 'RespondActivityTaskCompleted' or 'RespondActivityTaskFailed' once it is done
     * processing the task.
     * Application also needs to call 'RecordActivityTaskHeartbeat' API within 'heartbeatTimeoutSeconds' interval to
     * prevent the task from getting timed out.  An event 'ActivityTaskStarted' event is also written to workflow execution
     * history before the ActivityTask is dispatched to application worker.
     * @param \Temporal\Api\Workflowservice\V1\PollActivityTaskQueueRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function PollActivityTaskQueue(\Temporal\Api\Workflowservice\V1\PollActivityTaskQueueRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/PollActivityTaskQueue',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\PollActivityTaskQueueResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RecordActivityTaskHeartbeat is called by application worker while it is processing an ActivityTask.  If worker fails
     * to heartbeat within 'heartbeatTimeoutSeconds' interval for the ActivityTask, then it will be marked as timedout and
     * 'ActivityTaskTimedOut' event will be written to the workflow history.  Calling 'RecordActivityTaskHeartbeat' will
     * fail with 'NotFoundFailure' in such situations.  Use the 'taskToken' provided as response of
     * PollActivityTaskQueue API call for heart beating.
     * @param \Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RecordActivityTaskHeartbeat(\Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RecordActivityTaskHeartbeat',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     *     aip.dev/not-precedent: "By" is used to indicate request type. --)
     * RecordActivityTaskHeartbeatById is called by application worker while it is processing an ActivityTask.  If worker fails
     * to heartbeat within 'heartbeatTimeoutSeconds' interval for the ActivityTask, then it will be marked as timed out and
     * 'ActivityTaskTimedOut' event will be written to the workflow history.  Calling 'RecordActivityTaskHeartbeatById' will
     * fail with 'NotFoundFailure' in such situations.  Instead of using 'taskToken' like in RecordActivityTaskHeartbeat,
     * use Namespace, WorkflowId and ActivityId
     * @param \Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RecordActivityTaskHeartbeatById(\Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RecordActivityTaskHeartbeatById',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RecordActivityTaskHeartbeatByIdResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RespondActivityTaskCompleted is called by application worker when it is done processing an ActivityTask.  It will
     * result in a new 'ActivityTaskCompleted' event being written to the workflow history and a new WorkflowTask
     * created for the workflow so new commands could be made.  Use the 'taskToken' provided as response of
     * PollActivityTaskQueue API call for completion. It fails with 'NotFoundFailure' if the taskToken is not valid
     * anymore due to activity timeout.
     * @param \Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondActivityTaskCompleted(\Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondActivityTaskCompleted',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     *     aip.dev/not-precedent: "By" is used to indicate request type. --)
     * RespondActivityTaskCompletedById is called by application worker when it is done processing an ActivityTask.
     * It will result in a new 'ActivityTaskCompleted' event being written to the workflow history and a new WorkflowTask
     * created for the workflow so new commands could be made.  Similar to RespondActivityTaskCompleted but use Namespace,
     * WorkflowId and ActivityId instead of 'taskToken' for completion. It fails with 'NotFoundFailure'
     * if the these Ids are not valid anymore due to activity timeout.
     * @param \Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondActivityTaskCompletedById(\Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondActivityTaskCompletedById',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondActivityTaskCompletedByIdResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RespondActivityTaskFailed is called by application worker when it is done processing an ActivityTask.  It will
     * result in a new 'ActivityTaskFailed' event being written to the workflow history and a new WorkflowTask
     * created for the workflow instance so new commands could be made.  Use the 'taskToken' provided as response of
     * PollActivityTaskQueue API call for completion. It fails with 'NotFoundFailure' if the taskToken is not valid
     * anymore due to activity timeout.
     * @param \Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondActivityTaskFailed(\Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondActivityTaskFailed',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     *     aip.dev/not-precedent: "By" is used to indicate request type. --)
     * RespondActivityTaskFailedById is called by application worker when it is done processing an ActivityTask.
     * It will result in a new 'ActivityTaskFailed' event being written to the workflow history and a new WorkflowTask
     * created for the workflow instance so new commands could be made.  Similar to RespondActivityTaskFailed but use
     * Namespace, WorkflowId and ActivityId instead of 'taskToken' for completion. It fails with 'NotFoundFailure'
     * if the these Ids are not valid anymore due to activity timeout.
     * @param \Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondActivityTaskFailedById(\Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondActivityTaskFailedById',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondActivityTaskFailedByIdResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RespondActivityTaskCanceled is called by application worker when it is successfully canceled an ActivityTask.  It will
     * result in a new 'ActivityTaskCanceled' event being written to the workflow history and a new WorkflowTask
     * created for the workflow instance so new commands could be made.  Use the 'taskToken' provided as response of
     * PollActivityTaskQueue API call for completion. It fails with 'NotFoundFailure' if the taskToken is not valid
     * anymore due to activity timeout.
     * @param \Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondActivityTaskCanceled(\Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondActivityTaskCanceled',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     *     aip.dev/not-precedent: "By" is used to indicate request type. --)
     * RespondActivityTaskCanceledById is called by application worker when it is successfully canceled an ActivityTask.
     * It will result in a new 'ActivityTaskCanceled' event being written to the workflow history and a new WorkflowTask
     * created for the workflow instance so new commands could be made.  Similar to RespondActivityTaskCanceled but use
     * Namespace, WorkflowId and ActivityId instead of 'taskToken' for completion. It fails with 'NotFoundFailure'
     * if the these Ids are not valid anymore due to activity timeout.
     * @param \Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondActivityTaskCanceledById(\Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondActivityTaskCanceledById',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondActivityTaskCanceledByIdResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RequestCancelWorkflowExecution is called by application worker when it wants to request cancellation of a workflow instance.
     * It will result in a new 'WorkflowExecutionCancelRequested' event being written to the workflow history and a new WorkflowTask
     * created for the workflow instance so new commands could be made. It fails with 'NotFoundFailure' if the workflow is not valid
     * anymore due to completion or doesn't exist.
     * @param \Temporal\Api\Workflowservice\V1\RequestCancelWorkflowExecutionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RequestCancelWorkflowExecution(\Temporal\Api\Workflowservice\V1\RequestCancelWorkflowExecutionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RequestCancelWorkflowExecution',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RequestCancelWorkflowExecutionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * SignalWorkflowExecution is used to send a signal event to running workflow execution.  This results in
     * WorkflowExecutionSignaled event recorded in the history and a workflow task being created for the execution.
     * @param \Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function SignalWorkflowExecution(\Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/SignalWorkflowExecution',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\SignalWorkflowExecutionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * (-- api-linter: core::0136::prepositions=disabled
     *     aip.dev/not-precedent: "With" is used to indicate combined operation. --)
     * SignalWithStartWorkflowExecution is used to ensure sending signal to a workflow.
     * If the workflow is running, this results in WorkflowExecutionSignaled event being recorded in the history
     * and a workflow task being created for the execution.
     * If the workflow is not running or not found, this results in WorkflowExecutionStarted and WorkflowExecutionSignaled
     * events being recorded in history, and a workflow task being created for the execution
     * @param \Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function SignalWithStartWorkflowExecution(\Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/SignalWithStartWorkflowExecution',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\SignalWithStartWorkflowExecutionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ResetWorkflowExecution reset an existing workflow execution to WorkflowTaskCompleted event(exclusive).
     * And it will immediately terminating the current execution instance.
     * @param \Temporal\Api\Workflowservice\V1\ResetWorkflowExecutionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ResetWorkflowExecution(\Temporal\Api\Workflowservice\V1\ResetWorkflowExecutionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ResetWorkflowExecution',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ResetWorkflowExecutionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * TerminateWorkflowExecution terminates an existing workflow execution by recording WorkflowExecutionTerminated event
     * in the history and immediately terminating the execution instance.
     * @param \Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function TerminateWorkflowExecution(\Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/TerminateWorkflowExecution',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\TerminateWorkflowExecutionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ListOpenWorkflowExecutions is a visibility API to list the open executions in a specific namespace.
     * @param \Temporal\Api\Workflowservice\V1\ListOpenWorkflowExecutionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListOpenWorkflowExecutions(\Temporal\Api\Workflowservice\V1\ListOpenWorkflowExecutionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ListOpenWorkflowExecutions',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ListOpenWorkflowExecutionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ListClosedWorkflowExecutions is a visibility API to list the closed executions in a specific namespace.
     * @param \Temporal\Api\Workflowservice\V1\ListClosedWorkflowExecutionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListClosedWorkflowExecutions(\Temporal\Api\Workflowservice\V1\ListClosedWorkflowExecutionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ListClosedWorkflowExecutions',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ListClosedWorkflowExecutionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ListWorkflowExecutions is a visibility API to list workflow executions in a specific namespace.
     * @param \Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListWorkflowExecutions(\Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ListWorkflowExecutions',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ListArchivedWorkflowExecutions is a visibility API to list archived workflow executions in a specific namespace.
     * @param \Temporal\Api\Workflowservice\V1\ListArchivedWorkflowExecutionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListArchivedWorkflowExecutions(\Temporal\Api\Workflowservice\V1\ListArchivedWorkflowExecutionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ListArchivedWorkflowExecutions',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ListArchivedWorkflowExecutionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ScanWorkflowExecutions is a visibility API to list large amount of workflow executions in a specific namespace without order.
     * @param \Temporal\Api\Workflowservice\V1\ScanWorkflowExecutionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ScanWorkflowExecutions(\Temporal\Api\Workflowservice\V1\ScanWorkflowExecutionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ScanWorkflowExecutions',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ScanWorkflowExecutionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * CountWorkflowExecutions is a visibility API to count of workflow executions in a specific namespace.
     * @param \Temporal\Api\Workflowservice\V1\CountWorkflowExecutionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function CountWorkflowExecutions(\Temporal\Api\Workflowservice\V1\CountWorkflowExecutionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/CountWorkflowExecutions',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\CountWorkflowExecutionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * GetSearchAttributes is a visibility API to get all legal keys that could be used in list APIs
     * @param \Temporal\Api\Workflowservice\V1\GetSearchAttributesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetSearchAttributes(\Temporal\Api\Workflowservice\V1\GetSearchAttributesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/GetSearchAttributes',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\GetSearchAttributesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * RespondQueryTaskCompleted is called by application worker to complete a QueryTask (which is a WorkflowTask for query)
     * as a result of 'PollWorkflowTaskQueue' API call. Completing a QueryTask will unblock the client call to 'QueryWorkflow'
     * API and return the query result to client as a response to 'QueryWorkflow' API call.
     * @param \Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function RespondQueryTaskCompleted(\Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/RespondQueryTaskCompleted',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\RespondQueryTaskCompletedResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * ResetStickyTaskQueue resets the sticky task queue related information in mutable state of a given workflow.
     * Things cleared are:
     * 1. StickyTaskQueue
     * 2. StickyScheduleToStartTimeout
     * @param \Temporal\Api\Workflowservice\V1\ResetStickyTaskQueueRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ResetStickyTaskQueue(\Temporal\Api\Workflowservice\V1\ResetStickyTaskQueueRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ResetStickyTaskQueue',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ResetStickyTaskQueueResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * QueryWorkflow returns query result for a specified workflow execution
     * @param \Temporal\Api\Workflowservice\V1\QueryWorkflowRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function QueryWorkflow(\Temporal\Api\Workflowservice\V1\QueryWorkflowRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/QueryWorkflow',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\QueryWorkflowResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * DescribeWorkflowExecution returns information about the specified workflow execution.
     * @param \Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeWorkflowExecution(\Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/DescribeWorkflowExecution',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\DescribeWorkflowExecutionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * DescribeTaskQueue returns information about the target task queue, right now this API returns the
     * pollers which polled this task queue in last few minutes.
     * @param \Temporal\Api\Workflowservice\V1\DescribeTaskQueueRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function DescribeTaskQueue(\Temporal\Api\Workflowservice\V1\DescribeTaskQueueRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/DescribeTaskQueue',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\DescribeTaskQueueResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * GetClusterInfo returns information about temporal cluster
     * @param \Temporal\Api\Workflowservice\V1\GetClusterInfoRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function GetClusterInfo(\Temporal\Api\Workflowservice\V1\GetClusterInfoRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/GetClusterInfo',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\GetClusterInfoResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Temporal\Api\Workflowservice\V1\ListTaskQueuePartitionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function ListTaskQueuePartitions(\Temporal\Api\Workflowservice\V1\ListTaskQueuePartitionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/temporal.api.workflowservice.v1.WorkflowService/ListTaskQueuePartitions',
        $argument,
        ['\Temporal\Api\Workflowservice\V1\ListTaskQueuePartitionsResponse', 'decode'],
        $metadata, $options);
    }

}
