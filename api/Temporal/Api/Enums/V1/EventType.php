<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: temporal/api/enums/v1/event_type.proto

namespace Temporal\Api\Enums\V1;

use UnexpectedValueException;

/**
 * Whenever this list of events is changed do change the function shouldBufferEvent in mutableStateBuilder.go to make sure to do the correct event ordering.
 *
 * Protobuf type <code>temporal.api.enums.v1.EventType</code>
 */
class EventType
{
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_UNSPECIFIED = 0;</code>
     */
    const EVENT_TYPE_UNSPECIFIED = 0;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_STARTED = 1;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_STARTED = 1;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED = 2;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED = 2;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_FAILED = 3;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_FAILED = 3;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_TIMED_OUT = 4;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_TIMED_OUT = 4;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_TASK_SCHEDULED = 5;</code>
     */
    const EVENT_TYPE_WORKFLOW_TASK_SCHEDULED = 5;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_TASK_STARTED = 6;</code>
     */
    const EVENT_TYPE_WORKFLOW_TASK_STARTED = 6;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_TASK_COMPLETED = 7;</code>
     */
    const EVENT_TYPE_WORKFLOW_TASK_COMPLETED = 7;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_TASK_TIMED_OUT = 8;</code>
     */
    const EVENT_TYPE_WORKFLOW_TASK_TIMED_OUT = 8;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_TASK_FAILED = 9;</code>
     */
    const EVENT_TYPE_WORKFLOW_TASK_FAILED = 9;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_ACTIVITY_TASK_SCHEDULED = 10;</code>
     */
    const EVENT_TYPE_ACTIVITY_TASK_SCHEDULED = 10;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_ACTIVITY_TASK_STARTED = 11;</code>
     */
    const EVENT_TYPE_ACTIVITY_TASK_STARTED = 11;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_ACTIVITY_TASK_COMPLETED = 12;</code>
     */
    const EVENT_TYPE_ACTIVITY_TASK_COMPLETED = 12;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_ACTIVITY_TASK_FAILED = 13;</code>
     */
    const EVENT_TYPE_ACTIVITY_TASK_FAILED = 13;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_ACTIVITY_TASK_TIMED_OUT = 14;</code>
     */
    const EVENT_TYPE_ACTIVITY_TASK_TIMED_OUT = 14;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_ACTIVITY_TASK_CANCEL_REQUESTED = 15;</code>
     */
    const EVENT_TYPE_ACTIVITY_TASK_CANCEL_REQUESTED = 15;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_ACTIVITY_TASK_CANCELED = 16;</code>
     */
    const EVENT_TYPE_ACTIVITY_TASK_CANCELED = 16;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_TIMER_STARTED = 17;</code>
     */
    const EVENT_TYPE_TIMER_STARTED = 17;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_TIMER_FIRED = 18;</code>
     */
    const EVENT_TYPE_TIMER_FIRED = 18;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_TIMER_CANCELED = 19;</code>
     */
    const EVENT_TYPE_TIMER_CANCELED = 19;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_CANCEL_REQUESTED = 20;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_CANCEL_REQUESTED = 20;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_CANCELED = 21;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_CANCELED = 21;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED = 22;</code>
     */
    const EVENT_TYPE_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED = 22;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_FAILED = 23;</code>
     */
    const EVENT_TYPE_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_FAILED = 23;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_EXTERNAL_WORKFLOW_EXECUTION_CANCEL_REQUESTED = 24;</code>
     */
    const EVENT_TYPE_EXTERNAL_WORKFLOW_EXECUTION_CANCEL_REQUESTED = 24;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_MARKER_RECORDED = 25;</code>
     */
    const EVENT_TYPE_MARKER_RECORDED = 25;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_SIGNALED = 26;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_SIGNALED = 26;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_TERMINATED = 27;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_TERMINATED = 27;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW = 28;</code>
     */
    const EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW = 28;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED = 29;</code>
     */
    const EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED = 29;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_FAILED = 30;</code>
     */
    const EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_FAILED = 30;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_STARTED = 31;</code>
     */
    const EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_STARTED = 31;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_COMPLETED = 32;</code>
     */
    const EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_COMPLETED = 32;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_FAILED = 33;</code>
     */
    const EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_FAILED = 33;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_CANCELED = 34;</code>
     */
    const EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_CANCELED = 34;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TIMED_OUT = 35;</code>
     */
    const EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TIMED_OUT = 35;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TERMINATED = 36;</code>
     */
    const EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TERMINATED = 36;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED = 37;</code>
     */
    const EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED = 37;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_FAILED = 38;</code>
     */
    const EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_FAILED = 38;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_EXTERNAL_WORKFLOW_EXECUTION_SIGNALED = 39;</code>
     */
    const EVENT_TYPE_EXTERNAL_WORKFLOW_EXECUTION_SIGNALED = 39;
    /**
     * Generated from protobuf enum <code>EVENT_TYPE_UPSERT_WORKFLOW_SEARCH_ATTRIBUTES = 40;</code>
     */
    const EVENT_TYPE_UPSERT_WORKFLOW_SEARCH_ATTRIBUTES = 40;

    private static $valueToName = [
        self::EVENT_TYPE_UNSPECIFIED => 'EVENT_TYPE_UNSPECIFIED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_STARTED => 'EVENT_TYPE_WORKFLOW_EXECUTION_STARTED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED => 'EVENT_TYPE_WORKFLOW_EXECUTION_COMPLETED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_FAILED => 'EVENT_TYPE_WORKFLOW_EXECUTION_FAILED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_TIMED_OUT => 'EVENT_TYPE_WORKFLOW_EXECUTION_TIMED_OUT',
        self::EVENT_TYPE_WORKFLOW_TASK_SCHEDULED => 'EVENT_TYPE_WORKFLOW_TASK_SCHEDULED',
        self::EVENT_TYPE_WORKFLOW_TASK_STARTED => 'EVENT_TYPE_WORKFLOW_TASK_STARTED',
        self::EVENT_TYPE_WORKFLOW_TASK_COMPLETED => 'EVENT_TYPE_WORKFLOW_TASK_COMPLETED',
        self::EVENT_TYPE_WORKFLOW_TASK_TIMED_OUT => 'EVENT_TYPE_WORKFLOW_TASK_TIMED_OUT',
        self::EVENT_TYPE_WORKFLOW_TASK_FAILED => 'EVENT_TYPE_WORKFLOW_TASK_FAILED',
        self::EVENT_TYPE_ACTIVITY_TASK_SCHEDULED => 'EVENT_TYPE_ACTIVITY_TASK_SCHEDULED',
        self::EVENT_TYPE_ACTIVITY_TASK_STARTED => 'EVENT_TYPE_ACTIVITY_TASK_STARTED',
        self::EVENT_TYPE_ACTIVITY_TASK_COMPLETED => 'EVENT_TYPE_ACTIVITY_TASK_COMPLETED',
        self::EVENT_TYPE_ACTIVITY_TASK_FAILED => 'EVENT_TYPE_ACTIVITY_TASK_FAILED',
        self::EVENT_TYPE_ACTIVITY_TASK_TIMED_OUT => 'EVENT_TYPE_ACTIVITY_TASK_TIMED_OUT',
        self::EVENT_TYPE_ACTIVITY_TASK_CANCEL_REQUESTED => 'EVENT_TYPE_ACTIVITY_TASK_CANCEL_REQUESTED',
        self::EVENT_TYPE_ACTIVITY_TASK_CANCELED => 'EVENT_TYPE_ACTIVITY_TASK_CANCELED',
        self::EVENT_TYPE_TIMER_STARTED => 'EVENT_TYPE_TIMER_STARTED',
        self::EVENT_TYPE_TIMER_FIRED => 'EVENT_TYPE_TIMER_FIRED',
        self::EVENT_TYPE_TIMER_CANCELED => 'EVENT_TYPE_TIMER_CANCELED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_CANCEL_REQUESTED => 'EVENT_TYPE_WORKFLOW_EXECUTION_CANCEL_REQUESTED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_CANCELED => 'EVENT_TYPE_WORKFLOW_EXECUTION_CANCELED',
        self::EVENT_TYPE_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED => 'EVENT_TYPE_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED',
        self::EVENT_TYPE_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_FAILED => 'EVENT_TYPE_REQUEST_CANCEL_EXTERNAL_WORKFLOW_EXECUTION_FAILED',
        self::EVENT_TYPE_EXTERNAL_WORKFLOW_EXECUTION_CANCEL_REQUESTED => 'EVENT_TYPE_EXTERNAL_WORKFLOW_EXECUTION_CANCEL_REQUESTED',
        self::EVENT_TYPE_MARKER_RECORDED => 'EVENT_TYPE_MARKER_RECORDED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_SIGNALED => 'EVENT_TYPE_WORKFLOW_EXECUTION_SIGNALED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_TERMINATED => 'EVENT_TYPE_WORKFLOW_EXECUTION_TERMINATED',
        self::EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW => 'EVENT_TYPE_WORKFLOW_EXECUTION_CONTINUED_AS_NEW',
        self::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED => 'EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_INITIATED',
        self::EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_FAILED => 'EVENT_TYPE_START_CHILD_WORKFLOW_EXECUTION_FAILED',
        self::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_STARTED => 'EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_STARTED',
        self::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_COMPLETED => 'EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_COMPLETED',
        self::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_FAILED => 'EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_FAILED',
        self::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_CANCELED => 'EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_CANCELED',
        self::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TIMED_OUT => 'EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TIMED_OUT',
        self::EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TERMINATED => 'EVENT_TYPE_CHILD_WORKFLOW_EXECUTION_TERMINATED',
        self::EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED => 'EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_INITIATED',
        self::EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_FAILED => 'EVENT_TYPE_SIGNAL_EXTERNAL_WORKFLOW_EXECUTION_FAILED',
        self::EVENT_TYPE_EXTERNAL_WORKFLOW_EXECUTION_SIGNALED => 'EVENT_TYPE_EXTERNAL_WORKFLOW_EXECUTION_SIGNALED',
        self::EVENT_TYPE_UPSERT_WORKFLOW_SEARCH_ATTRIBUTES => 'EVENT_TYPE_UPSERT_WORKFLOW_SEARCH_ATTRIBUTES',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }


    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no value defined for name %s', __CLASS__, $name));
        }
        return constant($const);
    }
}

