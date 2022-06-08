<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: temporal/api/enums/v1/failed_cause.proto

namespace GPBMetadata\Temporal\Api\Enums\V1;

class FailedCause
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        $pool->internalAddGeneratedFile(
            '
�
(temporal/api/enums/v1/failed_cause.prototemporal.api.enums.v1*�
WorkflowTaskFailedCause*
&WORKFLOW_TASK_FAILED_CAUSE_UNSPECIFIED 0
,WORKFLOW_TASK_FAILED_CAUSE_UNHANDLED_COMMAND?
;WORKFLOW_TASK_FAILED_CAUSE_BAD_SCHEDULE_ACTIVITY_ATTRIBUTESE
AWORKFLOW_TASK_FAILED_CAUSE_BAD_REQUEST_CANCEL_ACTIVITY_ATTRIBUTES9
5WORKFLOW_TASK_FAILED_CAUSE_BAD_START_TIMER_ATTRIBUTES:
6WORKFLOW_TASK_FAILED_CAUSE_BAD_CANCEL_TIMER_ATTRIBUTES;
7WORKFLOW_TASK_FAILED_CAUSE_BAD_RECORD_MARKER_ATTRIBUTESI
EWORKFLOW_TASK_FAILED_CAUSE_BAD_COMPLETE_WORKFLOW_EXECUTION_ATTRIBUTESE
AWORKFLOW_TASK_FAILED_CAUSE_BAD_FAIL_WORKFLOW_EXECUTION_ATTRIBUTESG
CWORKFLOW_TASK_FAILED_CAUSE_BAD_CANCEL_WORKFLOW_EXECUTION_ATTRIBUTES	X
TWORKFLOW_TASK_FAILED_CAUSE_BAD_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_ATTRIBUTES
=
9WORKFLOW_TASK_FAILED_CAUSE_BAD_CONTINUE_AS_NEW_ATTRIBUTES7
3WORKFLOW_TASK_FAILED_CAUSE_START_TIMER_DUPLICATE_ID6
2WORKFLOW_TASK_FAILED_CAUSE_RESET_STICKY_TASK_QUEUE
@
<WORKFLOW_TASK_FAILED_CAUSE_WORKFLOW_WORKER_UNHANDLED_FAILUREG
CWORKFLOW_TASK_FAILED_CAUSE_BAD_SIGNAL_WORKFLOW_EXECUTION_ATTRIBUTESC
?WORKFLOW_TASK_FAILED_CAUSE_BAD_START_CHILD_EXECUTION_ATTRIBUTES2
.WORKFLOW_TASK_FAILED_CAUSE_FORCE_CLOSE_COMMAND5
1WORKFLOW_TASK_FAILED_CAUSE_FAILOVER_CLOSE_COMMAND4
0WORKFLOW_TASK_FAILED_CAUSE_BAD_SIGNAL_INPUT_SIZE-
)WORKFLOW_TASK_FAILED_CAUSE_RESET_WORKFLOW)
%WORKFLOW_TASK_FAILED_CAUSE_BAD_BINARY=
9WORKFLOW_TASK_FAILED_CAUSE_SCHEDULE_ACTIVITY_DUPLICATE_ID4
0WORKFLOW_TASK_FAILED_CAUSE_BAD_SEARCH_ATTRIBUTES6
2WORKFLOW_TASK_FAILED_CAUSE_NON_DETERMINISTIC_ERROR*�
&StartChildWorkflowExecutionFailedCause;
7START_CHILD_WORKFLOW_EXECUTION_FAILED_CAUSE_UNSPECIFIED G
CSTART_CHILD_WORKFLOW_EXECUTION_FAILED_CAUSE_WORKFLOW_ALREADY_EXISTSC
?START_CHILD_WORKFLOW_EXECUTION_FAILED_CAUSE_NAMESPACE_NOT_FOUND*�
*CancelExternalWorkflowExecutionFailedCause?
;CANCEL_EXTERNAL_WORKFLOW_EXECUTION_FAILED_CAUSE_UNSPECIFIED Y
UCANCEL_EXTERNAL_WORKFLOW_EXECUTION_FAILED_CAUSE_EXTERNAL_WORKFLOW_EXECUTION_NOT_FOUNDG
CCANCEL_EXTERNAL_WORKFLOW_EXECUTION_FAILED_CAUSE_NAMESPACE_NOT_FOUND*�
*SignalExternalWorkflowExecutionFailedCause?
;SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_FAILED_CAUSE_UNSPECIFIED Y
USIGNAL_EXTERNAL_WORKFLOW_EXECUTION_FAILED_CAUSE_EXTERNAL_WORKFLOW_EXECUTION_NOT_FOUNDG
CSIGNAL_EXTERNAL_WORKFLOW_EXECUTION_FAILED_CAUSE_NAMESPACE_NOT_FOUND*�
ResourceExhaustedCause(
$RESOURCE_EXHAUSTED_CAUSE_UNSPECIFIED &
"RESOURCE_EXHAUSTED_CAUSE_RPS_LIMIT-
)RESOURCE_EXHAUSTED_CAUSE_CONCURRENT_LIMIT.
*RESOURCE_EXHAUSTED_CAUSE_SYSTEM_OVERLOADEDB�
io.temporal.api.enums.v1BFailedCauseProtoPZ!go.temporal.io/api/enums/v1;enums�Temporal.Api.Enums.V1�Temporal::Api::Enums::V1bproto3'
        , true);

        static::$is_initialized = true;
    }
}

