<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Action;

use Google\Protobuf\Duration;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Client\Schedule\ScheduleAction;
use Temporal\Client\Schedule\WorkflowIdReusePolicy;
use Temporal\Common\RetryOptions;
use Temporal\Common\TaskQueue\TaskQueue;
use Temporal\DataConverter\EncodedCollection;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\EncodedCollectionType;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Traits\CloneWith;
use Temporal\Workflow\WorkflowType;

/**
 * Shared message that encapsulates all the required arguments to starting a Workflow in different contexts.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 *
 * @see \Temporal\Api\Workflow\V1\NewWorkflowExecutionInfo
 */
final class NewWorkflowExecutionInfo extends ScheduleAction
{
    use CloneWith;

    /**
     * Workflow ID
     */
    #[Marshal(name: 'workflow_id')]
    public readonly string $workflowId;

    /**
     * Workflow type
     */
    #[Marshal(name: 'workflow_type')]
    public readonly WorkflowType $workflowType;

    /**
     * Task queue
     */
    #[Marshal(name: 'task_queue')]
    public readonly TaskQueue $taskQueue;

    /**
     * Argument list to the workflow
     */
    #[Marshal(type: ObjectType::class, of: EncodedValues::class)]
    public readonly ValuesInterface $input;

    /**
     * Total workflow execution timeout including retries and continue as new
     */
    #[Marshal(name: 'workflow_execution_timeout', of: Duration::class)]
    public readonly \DateInterval $workflowExecutionTimeout;

    /**
     * Timeout of a single workflow run
     */
    #[Marshal(name: 'workflow_run_timeout', of: Duration::class)]
    public readonly \DateInterval $workflowRunTimeout;

    /**
     * Timeout of a single workflow task
     */
    #[Marshal(name: 'workflow_task_timeout', of: Duration::class)]
    public readonly \DateInterval $workflowTaskTimeout;

    /**
     * Default {@see WorkflowIdReusePolicy::WorkflowIdReusePolicyAllowDuplicate}
     */
    #[Marshal(name: 'workflow_id_reuse_policy')]
    public readonly WorkflowIdReusePolicy $workflowIdReusePolicy;

    /**
     * The retry policy for the workflow. Will never exceed {@see self::$workflowExecutionTimeout}.
     */
    #[Marshal(name: 'retry_policy')]
    public readonly RetryOptions $retryPolicy;

    /**
     * @link https://docs.temporal.io/docs/content/what-is-a-temporal-cron-job/
     */
    #[Marshal(name: 'cron_schedule')]
    public readonly string $cronSchedule;

    /**
     * Memo
     */
    #[Marshal(name: 'memo', type: EncodedCollectionType::class, of: Memo::class)]
    public readonly EncodedCollection $memo;

    /**
     * Search attributes
     */
    #[Marshal(name: 'search_attributes', type: EncodedCollectionType::class, of: SearchAttributes::class)]
    public readonly EncodedCollection $searchAttributes;

    /**
     * Header
     * todo: make Header compatible with EncodedCollection
     */
    #[Marshal(type: EncodedCollectionType::class, of: Header::class)]
    public readonly EncodedCollection|HeaderInterface $header;

    private function __construct(WorkflowType $workflowType, TaskQueue $taskQueue)
    {
        $this->workflowId = '';
        $this->workflowType = $workflowType;
        $this->taskQueue = $taskQueue;
        $this->input = EncodedValues::empty();
        $this->workflowExecutionTimeout = new \DateInterval('PT0S');
        $this->workflowRunTimeout = new \DateInterval('PT0S');
        $this->workflowTaskTimeout = new \DateInterval('PT0S');
        $this->workflowIdReusePolicy = WorkflowIdReusePolicy::WorkflowIdReusePolicyUnspecified;
        $this->retryPolicy = RetryOptions::new();
        $this->cronSchedule = '';
        $this->memo = EncodedCollection::empty();
        $this->searchAttributes = EncodedCollection::empty();
        $this->header = \Temporal\Interceptor\Header::empty();
    }

    public static function new(string|WorkflowType $workflowType, string|TaskQueue $taskQueue): self
    {
        \is_string($workflowType) and $workflowType = self::createWorkflowType($workflowType);
        \is_string($taskQueue) and $taskQueue = TaskQueue::new($taskQueue);

        return new self($workflowType, $taskQueue);
    }

    public function withWorkflowId(string $workflowId): self
    {
        /** @see self::$workflowId */
        return $this->with('workflowId', $workflowId);
    }

    public function withWorkflowType(string|WorkflowType $workflowType): self
    {
        \is_string($workflowType) and $workflowType = self::createWorkflowType($workflowType);

        /** @see self::$workflowType */
        return $this->with('workflowType', $workflowType);
    }

    public function withTaskQueue(string|TaskQueue $taskQueue): self
    {
        \is_string($taskQueue) and $taskQueue = TaskQueue::new($taskQueue);

        /** @see self::$taskQueue */
        return $this->with('taskQueue', $taskQueue);
    }

    /**
     * Arguments to the workflow
     *
     * @param list<mixed>|ValuesInterface $input
     */
    public function withInput(array|ValuesInterface $input): self
    {
        \is_array($input) and $input = EncodedValues::fromValues($input);

        /** @see self::$input */
        return $this->with('input', $input);
    }

    /**
     * Total workflow execution timeout including retries and continue as new
     *
     * @param DateIntervalValue $timeout
     */
    public function withWorkflowExecutionTimeout(mixed $timeout): self
    {
        $timeout = $timeout === null
            ? new \DateInterval('PT0S')
            : DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);

        /** @see self::$workflowExecutionTimeout */
        return $this->with('workflowExecutionTimeout', $timeout);
    }

    /**
     * Timeout of a single workflow run
     *
     * @param DateIntervalValue $timeout
     */
    public function withWorkflowRunTimeout(mixed $timeout): self
    {
        $timeout = $timeout === null
            ? new \DateInterval('PT0S')
            : DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);

        /** @see self::$workflowRunTimeout */
        return $this->with('workflowRunTimeout', $timeout);
    }

    /**
     * Timeout of a single workflow task
     *
     * @param DateIntervalValue $timeout
     */
    public function withWorkflowTaskTimeout(mixed $timeout): self
    {
        $timeout = $timeout === null
            ? new \DateInterval('PT0S')
            : DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);

        /** @see self::$workflowTaskTimeout */
        return $this->with('workflowTaskTimeout', $timeout);
    }

    public function withWorkflowIdReusePolicy(WorkflowIdReusePolicy $policy): self
    {
        /** @see self::$workflowIdReusePolicy */
        return $this->with('workflowIdReusePolicy', $policy);
    }

    /**
     * The retry policy for the workflow. Will never exceed {@see self::$workflowExecutionTimeout}
     */
    public function withRetryPolicy(RetryOptions $retryPolicy): self
    {
        /** @see self::$retryPolicy */
        return $this->with('retryPolicy', $retryPolicy);
    }

    /**
     * @link https://docs.temporal.io/docs/content/what-is-a-temporal-cron-job/
     */
    public function withCronSchedule(?string $cronSchedule): self
    {
        /** @see self::$cronSchedule */
        return $this->with('cronSchedule', (string)$cronSchedule);
    }

    /**
     * Memo
     *
     * @param array<non-empty-string, mixed>|EncodedCollection $memo
     */
    public function withMemo(array|EncodedCollection $memo): self
    {
        \is_array($memo) and $memo = EncodedCollection::fromValues($memo);

        /** @see self::$memo */
        return $this->with('memo', $memo);
    }

    /**
     * Search attributes
     *
     * @param array<non-empty-string, mixed>|EncodedCollection $searchAttributes
     */
    public function withSearchAttributes(array|EncodedCollection $searchAttributes): self
    {
        \is_array($searchAttributes) and $searchAttributes = EncodedCollection::fromValues($searchAttributes);

        /** @see self::$searchAttributes */
        return $this->with('searchAttributes', $searchAttributes);
    }

    /**
     * Header
     *
     * @param array<non-empty-string, mixed>|EncodedCollection $header
     */
    public function withHeader(array|HeaderInterface $header): self
    {
        \is_array($header) and $header = \Temporal\Interceptor\Header::fromValues($header);

        /** @see self::$header */
        return $this->with('header', $header);
    }

    private static function createWorkflowType(string $name): WorkflowType
    {
        $workflowType = new WorkflowType();
        $workflowType->name = $name;
        return $workflowType;
    }
}
