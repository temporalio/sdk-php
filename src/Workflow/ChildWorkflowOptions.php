<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Workflow;

use Carbon\CarbonInterval;
use JetBrains\PhpStorm\Pure;
use Temporal\Client\Common\CronSchedule;
use Temporal\Client\Common\RetryOptions;
use Temporal\Client\Common\Uuid;
use Temporal\Client\Exception\FailedCancellationException;
use Temporal\Client\Internal\Assert;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Type\ArrayType;
use Temporal\Client\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Client\Internal\Marshaller\Type\NullableType;
use Temporal\Client\Internal\Marshaller\Type\ObjectType;
use Temporal\Client\Internal\Support\DateInterval;
use Temporal\Client\Worker;
use Temporal\Client\Worker\FactoryInterface;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 */
final class ChildWorkflowOptions
{
    /**
     * Namespace of the child workflow.
     *
     * Optional: the current workflow (parent)'s namespace will be used if this
     * is not provided.
     */
    #[Marshal(name: 'Namespace')]
    public string $namespace = 'default';

    /**
     * WorkflowID of the child workflow to be scheduled.
     *
     * Optional: an auto generated workflowID will be used if this is not
     * provided.
     */
    #[Marshal(name: 'WorkflowID')]
    public string $workflowId;

    /**
     * TaskQueue that the child workflow needs to be scheduled on.
     *
     * Optional: the parent workflow task queue will be used if this is not
     * provided.
     */
    #[Marshal(name: 'TaskQueue')]
    public string $taskQueue = FactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * The end to end timeout for the child workflow execution including
     * retries and continue as new.
     *
     * Optional: defaults to 10 years
     */
    #[Marshal(name: 'WorkflowExecutionTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowExecutionTimeout;

    /**
     * The timeout for a single run of the child workflow execution. Each retry
     * or continue as new should obey this timeout. Use WorkflowExecutionTimeout
     * to specify how long the parent is willing to wait for the child
     * completion.
     *
     * Optional: defaults to WorkflowExecutionTimeout
     */
    #[Marshal(name: 'WorkflowRunTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowRunTimeout;

    /**
     * The workflow task timeout for the child workflow.
     *
     * Optional: default is 10s if this is not provided (or if 0 is provided).
     */
    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowTaskTimeout;

    /**
     * In case of a child workflow cancellation it fails with
     * a {@see FailedCancellationException}. The type defines at which point
     * the exception is thrown.
     *
     * @psalm-var ChildWorkflowCancellationType::*
     */
    #[Marshal(name: 'WaitForCancellation', type: ChildWorkflowCancellationType::class)]
    public int $cancellationType = ChildWorkflowCancellationType::TRY_CANCEL;

    /**
     * Whether server allow reuse of workflow ID, can be useful for dedup
     * logic if set to {@see IdReusePolicy::POLICY_REJECT_DUPLICATE}.
     *
     * @psalm-var IdReusePolicy::POLICY_*
     */
    #[Marshal(name: 'WorkflowIDReusePolicy')]
    public int $workflowIdReusePolicy = IdReusePolicy::POLICY_ALLOW_DUPLICATE_FAILED_ONLY;

    /**
     * RetryPolicy specify how to retry child workflow if error happens.
     *
     * Optional: default is no retry.
     */
    #[Marshal(name: 'RetryPolicy', type: ObjectType::class, of: RetryOptions::class)]
    public RetryOptions $retryOptions;

    /**
     * Optional cron schedule for workflow.
     */
    #[Marshal(name: 'CronSchedule')]
    public ?string $cronSchedule = null;

    /**
     * Optional policy to decide what to do for the child.
     *
     * Default is Terminate (if onboarded to this feature).
     *
     * @psalm-var ParentClosePolicy::POLICY_*
     */
    #[Marshal(name: 'ParentClosePolicy')]
    public int $parentClosePolicy = ParentClosePolicy::POLICY_TERMINATE;

    /**
     * Optional non-indexed info that will be shown in list workflow.
     */
    #[Marshal(name: 'Memo', type: NullableType::class, of: ArrayType::class)]
    public ?array $memo = null;

    /**
     * Optional indexed info that can be used in query of List/Scan/Count
     * workflow APIs (only supported when Temporal server is using
     * ElasticSearch). The key and value type must be registered on Temporal
     * server side.
     */
    #[Marshal(name: 'SearchAttributes', type: NullableType::class, of: ArrayType::class)]
    public ?array $searchAttributes = null;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->workflowId = Uuid::v4();
        $this->workflowExecutionTimeout = $this->workflowRunTimeout =
            CarbonInterval::years(10);
        $this->workflowTaskTimeout = CarbonInterval::seconds(10);
        $this->retryOptions = new RetryOptions();
    }

    /**
     * @return static
     */
    public static function new(): self
    {
        return new static();
    }

    /**
     * Specify namespace in which workflow should be started.
     *
     * @param string $namespace
     * @return $this
     */
    public function withNamespace(string $namespace): self
    {
        return immutable(fn() => $this->namespace = $namespace);
    }

    /**
     * Workflow id to use when starting. If not specified a UUID is generated.
     * Note that it is dangerous as in case of client side retries no
     * deduplication will happen based on the generated id. So prefer assigning
     * business meaningful ids if possible.
     *
     * @param string $workflowId
     * @return $this
     */
    public function withWorkflowId(string $workflowId): self
    {
        return immutable(fn() => $this->workflowId = $workflowId);
    }

    /**
     * Task queue to use for workflow tasks. It should match a task queue
     * specified when creating a {@see Worker\TaskQueue} that hosts the
     * workflow code.
     *
     * @param string $taskQueue
     * @return $this
     */
    public function withTaskQueue(string $taskQueue): self
    {
        return immutable(fn() => $this->taskQueue = $taskQueue);
    }

    /**
     * The maximum time that parent workflow is willing to wait for a child
     * execution (which includes retries and continue as new calls). If exceeded
     * the child is automatically terminated by the Temporal service.
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    public function withWorkflowExecutionTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));

        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);

        assert($timeout->totalMicroseconds >= 0);

        return immutable(fn() => $this->workflowExecutionTimeout = $timeout);
    }

    /**
     * The time after which workflow run is automatically terminated by the
     * Temporal service. Do not rely on the run timeout for business level
     * timeouts. It is preferred to use in workflow timers for this purpose.
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    public function withWorkflowRunTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));

        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);

        assert($timeout->totalMicroseconds >= 0);

        return immutable(fn() => $this->workflowRunTimeout = $timeout);
    }

    /**
     * Maximum execution time of a single workflow task. Default is 10 seconds.
     * Maximum accepted value is 60 seconds.
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    public function withWorkflowTaskTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));

        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);

        assert($timeout->totalMicroseconds >= 0 && $timeout->totalSeconds <= 60);

        return immutable(fn() => $this->workflowTaskTimeout = $timeout);
    }

    /**
     * In case of a child workflow cancellation it fails with
     * a {@see FailedCancellationException}. The type defines at which point
     * the exception is thrown.
     *
     * @param ChildWorkflowCancellationType::* $type
     * @return $this
     */
    public function withChildWorkflowCancellationType(int $type): self
    {
        assert(Assert::enum($type, ChildWorkflowCancellationType::class));

        return immutable(fn () => $this->cancellationType = $type);
    }

    /**
     * Specifies server behavior if a completed workflow with the same id
     * exists. Note that under no conditions Temporal allows two workflows
     * with the same namespace and workflow id run simultaneously.
     *
     * - {@see IdReusePolicy::POLICY_ALLOW_DUPLICATE_FAILED_ONLY}: Is a default
     *  value. It means that workflow can start if previous run failed or was
     *  canceled or terminated.
     *
     * - {@see IdReusePolicy::POLICY_ALLOW_DUPLICATE}: Allows new run
     *  independently of the previous run closure status.
     *
     * - {@see IdReusePolicy::POLICY_REJECT_DUPLICATE}: Doesn't allow new run
     *  independently of the previous run closure status.
     *
     * @param IdReusePolicy::* $policy
     * @return $this
     */
    public function withWorkflowIdReusePolicy(int $policy): self
    {
        assert(Assert::enum($policy, IdReusePolicy::class));

        return immutable(fn() => $this->workflowIdReusePolicy = $policy);
    }

    /**
     * RetryOptions that define how child workflow is retried in case of
     * failure. Default is null which is no reties.
     *
     * @param RetryOptions|null $options
     * @return $this
     */
    public function withRetryOptions(?RetryOptions $options): self
    {
        return immutable(fn() => $this->retryOptions = $options ?? new RetryOptions());
    }

    /**
     * @param string|null $cronSchedule
     * @return $this
     */
    public function withCronSchedule(?string $cronSchedule): self
    {
        return immutable(fn () => $this->cronSchedule = $cronSchedule);
    }

    /**
     * Specifies additional non-indexed information in result of list workflow.
     *
     * @param array|null $memo
     * @return $this
     */
    public function withMemo(?array $memo): self
    {
        return immutable(fn () => $this->memo = $memo);
    }

    /**
     * Specifies additional indexed information in result of list workflow.
     *
     * @param array|null $searchAttributes
     * @return $this
     */
    public function withSearchAttributes(?array $searchAttributes): self
    {
        return immutable(fn () => $this->searchAttributes = $searchAttributes);
    }

    /**
     * Specifies how this workflow reacts to the death of the parent workflow.
     *
     * @param ParentClosePolicy::* $policy
     * @return $this
     */
    public function withParentClosePolicy(int $policy): self
    {
        assert(Assert::enum($policy, ParentClosePolicy::class));

        return immutable(fn () => $this->parentClosePolicy = $policy);
    }
}
