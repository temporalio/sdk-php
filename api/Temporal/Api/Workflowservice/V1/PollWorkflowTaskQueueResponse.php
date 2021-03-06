<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: temporal/api/workflowservice/v1/request_response.proto

namespace Temporal\Api\Workflowservice\V1;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>temporal.api.workflowservice.v1.PollWorkflowTaskQueueResponse</code>
 */
class PollWorkflowTaskQueueResponse extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>bytes task_token = 1;</code>
     */
    protected $task_token = '';
    /**
     * Generated from protobuf field <code>.temporal.api.common.v1.WorkflowExecution workflow_execution = 2;</code>
     */
    protected $workflow_execution = null;
    /**
     * Generated from protobuf field <code>.temporal.api.common.v1.WorkflowType workflow_type = 3;</code>
     */
    protected $workflow_type = null;
    /**
     * Generated from protobuf field <code>int64 previous_started_event_id = 4;</code>
     */
    protected $previous_started_event_id = 0;
    /**
     * Generated from protobuf field <code>int64 started_event_id = 5;</code>
     */
    protected $started_event_id = 0;
    /**
     * Generated from protobuf field <code>int32 attempt = 6;</code>
     */
    protected $attempt = 0;
    /**
     * Generated from protobuf field <code>int64 backlog_count_hint = 7;</code>
     */
    protected $backlog_count_hint = 0;
    /**
     * Generated from protobuf field <code>.temporal.api.history.v1.History history = 8;</code>
     */
    protected $history = null;
    /**
     * Generated from protobuf field <code>bytes next_page_token = 9;</code>
     */
    protected $next_page_token = '';
    /**
     * Generated from protobuf field <code>.temporal.api.query.v1.WorkflowQuery query = 10;</code>
     */
    protected $query = null;
    /**
     * Generated from protobuf field <code>.temporal.api.taskqueue.v1.TaskQueue workflow_execution_task_queue = 11;</code>
     */
    protected $workflow_execution_task_queue = null;
    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp scheduled_time = 12 [(.gogoproto.stdtime) = true];</code>
     */
    protected $scheduled_time = null;
    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp started_time = 13 [(.gogoproto.stdtime) = true];</code>
     */
    protected $started_time = null;
    /**
     * Generated from protobuf field <code>map<string, .temporal.api.query.v1.WorkflowQuery> queries = 14;</code>
     */
    private $queries;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $task_token
     *     @type \Temporal\Api\Common\V1\WorkflowExecution $workflow_execution
     *     @type \Temporal\Api\Common\V1\WorkflowType $workflow_type
     *     @type int|string $previous_started_event_id
     *     @type int|string $started_event_id
     *     @type int $attempt
     *     @type int|string $backlog_count_hint
     *     @type \Temporal\Api\History\V1\History $history
     *     @type string $next_page_token
     *     @type \Temporal\Api\Query\V1\WorkflowQuery $query
     *     @type \Temporal\Api\Taskqueue\V1\TaskQueue $workflow_execution_task_queue
     *     @type \Google\Protobuf\Timestamp $scheduled_time
     *     @type \Google\Protobuf\Timestamp $started_time
     *     @type array|\Google\Protobuf\Internal\MapField $queries
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\Temporal\Api\Workflowservice\V1\RequestResponse::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>bytes task_token = 1;</code>
     * @return string
     */
    public function getTaskToken()
    {
        return $this->task_token;
    }

    /**
     * Generated from protobuf field <code>bytes task_token = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setTaskToken($var)
    {
        GPBUtil::checkString($var, False);
        $this->task_token = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.temporal.api.common.v1.WorkflowExecution workflow_execution = 2;</code>
     * @return \Temporal\Api\Common\V1\WorkflowExecution
     */
    public function getWorkflowExecution()
    {
        return isset($this->workflow_execution) ? $this->workflow_execution : null;
    }

    public function hasWorkflowExecution()
    {
        return isset($this->workflow_execution);
    }

    public function clearWorkflowExecution()
    {
        unset($this->workflow_execution);
    }

    /**
     * Generated from protobuf field <code>.temporal.api.common.v1.WorkflowExecution workflow_execution = 2;</code>
     * @param \Temporal\Api\Common\V1\WorkflowExecution $var
     * @return $this
     */
    public function setWorkflowExecution($var)
    {
        GPBUtil::checkMessage($var, \Temporal\Api\Common\V1\WorkflowExecution::class);
        $this->workflow_execution = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.temporal.api.common.v1.WorkflowType workflow_type = 3;</code>
     * @return \Temporal\Api\Common\V1\WorkflowType
     */
    public function getWorkflowType()
    {
        return isset($this->workflow_type) ? $this->workflow_type : null;
    }

    public function hasWorkflowType()
    {
        return isset($this->workflow_type);
    }

    public function clearWorkflowType()
    {
        unset($this->workflow_type);
    }

    /**
     * Generated from protobuf field <code>.temporal.api.common.v1.WorkflowType workflow_type = 3;</code>
     * @param \Temporal\Api\Common\V1\WorkflowType $var
     * @return $this
     */
    public function setWorkflowType($var)
    {
        GPBUtil::checkMessage($var, \Temporal\Api\Common\V1\WorkflowType::class);
        $this->workflow_type = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int64 previous_started_event_id = 4;</code>
     * @return int|string
     */
    public function getPreviousStartedEventId()
    {
        return $this->previous_started_event_id;
    }

    /**
     * Generated from protobuf field <code>int64 previous_started_event_id = 4;</code>
     * @param int|string $var
     * @return $this
     */
    public function setPreviousStartedEventId($var)
    {
        GPBUtil::checkInt64($var);
        $this->previous_started_event_id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int64 started_event_id = 5;</code>
     * @return int|string
     */
    public function getStartedEventId()
    {
        return $this->started_event_id;
    }

    /**
     * Generated from protobuf field <code>int64 started_event_id = 5;</code>
     * @param int|string $var
     * @return $this
     */
    public function setStartedEventId($var)
    {
        GPBUtil::checkInt64($var);
        $this->started_event_id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 attempt = 6;</code>
     * @return int
     */
    public function getAttempt()
    {
        return $this->attempt;
    }

    /**
     * Generated from protobuf field <code>int32 attempt = 6;</code>
     * @param int $var
     * @return $this
     */
    public function setAttempt($var)
    {
        GPBUtil::checkInt32($var);
        $this->attempt = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int64 backlog_count_hint = 7;</code>
     * @return int|string
     */
    public function getBacklogCountHint()
    {
        return $this->backlog_count_hint;
    }

    /**
     * Generated from protobuf field <code>int64 backlog_count_hint = 7;</code>
     * @param int|string $var
     * @return $this
     */
    public function setBacklogCountHint($var)
    {
        GPBUtil::checkInt64($var);
        $this->backlog_count_hint = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.temporal.api.history.v1.History history = 8;</code>
     * @return \Temporal\Api\History\V1\History
     */
    public function getHistory()
    {
        return isset($this->history) ? $this->history : null;
    }

    public function hasHistory()
    {
        return isset($this->history);
    }

    public function clearHistory()
    {
        unset($this->history);
    }

    /**
     * Generated from protobuf field <code>.temporal.api.history.v1.History history = 8;</code>
     * @param \Temporal\Api\History\V1\History $var
     * @return $this
     */
    public function setHistory($var)
    {
        GPBUtil::checkMessage($var, \Temporal\Api\History\V1\History::class);
        $this->history = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bytes next_page_token = 9;</code>
     * @return string
     */
    public function getNextPageToken()
    {
        return $this->next_page_token;
    }

    /**
     * Generated from protobuf field <code>bytes next_page_token = 9;</code>
     * @param string $var
     * @return $this
     */
    public function setNextPageToken($var)
    {
        GPBUtil::checkString($var, False);
        $this->next_page_token = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.temporal.api.query.v1.WorkflowQuery query = 10;</code>
     * @return \Temporal\Api\Query\V1\WorkflowQuery
     */
    public function getQuery()
    {
        return isset($this->query) ? $this->query : null;
    }

    public function hasQuery()
    {
        return isset($this->query);
    }

    public function clearQuery()
    {
        unset($this->query);
    }

    /**
     * Generated from protobuf field <code>.temporal.api.query.v1.WorkflowQuery query = 10;</code>
     * @param \Temporal\Api\Query\V1\WorkflowQuery $var
     * @return $this
     */
    public function setQuery($var)
    {
        GPBUtil::checkMessage($var, \Temporal\Api\Query\V1\WorkflowQuery::class);
        $this->query = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.temporal.api.taskqueue.v1.TaskQueue workflow_execution_task_queue = 11;</code>
     * @return \Temporal\Api\Taskqueue\V1\TaskQueue
     */
    public function getWorkflowExecutionTaskQueue()
    {
        return isset($this->workflow_execution_task_queue) ? $this->workflow_execution_task_queue : null;
    }

    public function hasWorkflowExecutionTaskQueue()
    {
        return isset($this->workflow_execution_task_queue);
    }

    public function clearWorkflowExecutionTaskQueue()
    {
        unset($this->workflow_execution_task_queue);
    }

    /**
     * Generated from protobuf field <code>.temporal.api.taskqueue.v1.TaskQueue workflow_execution_task_queue = 11;</code>
     * @param \Temporal\Api\Taskqueue\V1\TaskQueue $var
     * @return $this
     */
    public function setWorkflowExecutionTaskQueue($var)
    {
        GPBUtil::checkMessage($var, \Temporal\Api\Taskqueue\V1\TaskQueue::class);
        $this->workflow_execution_task_queue = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp scheduled_time = 12 [(.gogoproto.stdtime) = true];</code>
     * @return \Google\Protobuf\Timestamp
     */
    public function getScheduledTime()
    {
        return isset($this->scheduled_time) ? $this->scheduled_time : null;
    }

    public function hasScheduledTime()
    {
        return isset($this->scheduled_time);
    }

    public function clearScheduledTime()
    {
        unset($this->scheduled_time);
    }

    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp scheduled_time = 12 [(.gogoproto.stdtime) = true];</code>
     * @param \Google\Protobuf\Timestamp $var
     * @return $this
     */
    public function setScheduledTime($var)
    {
        GPBUtil::checkMessage($var, \Google\Protobuf\Timestamp::class);
        $this->scheduled_time = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp started_time = 13 [(.gogoproto.stdtime) = true];</code>
     * @return \Google\Protobuf\Timestamp
     */
    public function getStartedTime()
    {
        return isset($this->started_time) ? $this->started_time : null;
    }

    public function hasStartedTime()
    {
        return isset($this->started_time);
    }

    public function clearStartedTime()
    {
        unset($this->started_time);
    }

    /**
     * Generated from protobuf field <code>.google.protobuf.Timestamp started_time = 13 [(.gogoproto.stdtime) = true];</code>
     * @param \Google\Protobuf\Timestamp $var
     * @return $this
     */
    public function setStartedTime($var)
    {
        GPBUtil::checkMessage($var, \Google\Protobuf\Timestamp::class);
        $this->started_time = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>map<string, .temporal.api.query.v1.WorkflowQuery> queries = 14;</code>
     * @return \Google\Protobuf\Internal\MapField
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * Generated from protobuf field <code>map<string, .temporal.api.query.v1.WorkflowQuery> queries = 14;</code>
     * @param array|\Google\Protobuf\Internal\MapField $var
     * @return $this
     */
    public function setQueries($var)
    {
        $arr = GPBUtil::checkMapField($var, \Google\Protobuf\Internal\GPBType::STRING, \Google\Protobuf\Internal\GPBType::MESSAGE, \Temporal\Api\Query\V1\WorkflowQuery::class);
        $this->queries = $arr;

        return $this;
    }

}

