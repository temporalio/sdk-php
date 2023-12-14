<?php

declare(strict_types=1);

namespace Temporal\Client\Schedule\Action;

use Google\Protobuf\Duration;
use Temporal\Api\Common\V1\Header;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Common\IdReusePolicy;
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
 * Start a new Workflow execution.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 *
 * @see \Temporal\Api\Workflow\V1\NewWorkflowExecutionInfo
 */
final class StartWorkflowAction extends ScheduleAction
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
     * Default {@see IdReusePolicy::AllowDuplicate}
     */
    #[Marshal(name: 'workflow_id_reuse_policy')]
    public readonly IdReusePolicy $workflowIdReusePolicy;

    /**
     * The retry policy for the workflow. Will never exceed {@see self::$workflowExecutionTimeout}.
     */
    #[Marshal(name: 'retry_policy')]
    public readonly RetryOptions $retryPolicy;

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
     */
    #[Marshal(type: EncodedCollectionType::class, of: Header::class)]
    public readonly HeaderInterface $header;

    private function __construct(WorkflowType $workflowType)
    {
        $this->workflowId = '';
        $this->workflowType = $workflowType;
        $this->taskQueue = TaskQueue::new('default');
        $this->input = EncodedValues::empty();
        $this->workflowExecutionTimeout = new \DateInterval('PT0S');
        $this->workflowRunTimeout = new \DateInterval('PT0S');
        $this->workflowTaskTimeout = new \DateInterval('PT0S');
        $this->workflowIdReusePolicy = IdReusePolicy::Unspecified;
        $this->retryPolicy = RetryOptions::new();
        $this->memo = EncodedCollection::empty();
        $this->searchAttributes = EncodedCollection::empty();
        $this->header = \Temporal\Interceptor\Header::empty();
    }

    public static function new(string|WorkflowType $workflowType): self
    {
        \is_string($workflowType) and $workflowType = self::createWorkflowType($workflowType);

        return new self($workflowType);
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
     * @param list<mixed>|ValuesInterface $values
     */
    public function withInput(array|ValuesInterface $values): self
    {
        $values instanceof ValuesInterface or $values = EncodedValues::fromValues($values);

        /** @see self::$input */
        return $this->with('input', $values);
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

    public function withWorkflowIdReusePolicy(IdReusePolicy $policy): self
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
     * Memo
     *
     * @param iterable<non-empty-string, mixed>|EncodedCollection $values
     */
    public function withMemo(iterable|EncodedCollection $values): self
    {
        $values instanceof EncodedCollection or $values = EncodedCollection::fromValues($values);

        /** @see self::$memo */
        return $this->with('memo', $values);
    }

    /**
     * Search attributes
     *
     * @param iterable<non-empty-string, mixed>|EncodedCollection $values
     */
    public function withSearchAttributes(iterable|EncodedCollection $values): self
    {
        $values instanceof EncodedCollection or $values = EncodedCollection::fromValues($values);

        /** @see self::$searchAttributes */
        return $this->with('searchAttributes', $values);
    }

    /**
     * Header
     *
     * @param iterable<non-empty-string, mixed>|HeaderInterface $values
     */
    public function withHeader(iterable|HeaderInterface $values): self
    {
        $values instanceof HeaderInterface or $values = \Temporal\Interceptor\Header::fromValues($values);

        /** @see self::$header */
        return $this->with('header', $values);
    }

    private static function createWorkflowType(string $name): WorkflowType
    {
        $workflowType = new WorkflowType();
        $workflowType->name = $name;
        return $workflowType;
    }
}
