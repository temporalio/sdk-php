<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Carbon\CarbonInterval;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use Temporal\Client\ClientOptions;
use Temporal\Common\CronSchedule;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\MethodRetry;
use Temporal\Common\RetryOptions;
use Temporal\Exception\FailedCancellationException;
use Temporal\Internal\Assert;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\CronType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;
use Temporal\Worker\WorkerFactoryInterface;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 * @psalm-import-type ChildWorkflowCancellationEnum from ChildWorkflowCancellationType
 */
final class ChildWorkflowOptions extends Options
{
    /**
     * Namespace of the child workflow.
     *
     * Optional: the current workflow (parent)'s namespace will be used if this
     * is not provided.
     */
    #[Marshal(name: 'Namespace')]
    public string $namespace = ClientOptions::DEFAULT_NAMESPACE;

    /**
     * WorkflowID of the child workflow to be scheduled.
     *
     * Optional: an auto generated workflowID will be used if this is not
     * provided.
     */
    #[Marshal(name: 'WorkflowID')]
    public ?string $workflowId = null;

    /**
     * TaskQueue that the child workflow needs to be scheduled on.
     *
     * Optional: the parent workflow task queue will be used if this is not
     * provided.
     */
    #[Marshal(name: 'TaskQueueName')]
    public string $taskQueue = WorkerFactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * The end to end timeout for the child workflow execution including
     * retries and continue as new.
     *
     * Optional: defaults is no limit
     */
    #[Marshal(name: 'WorkflowExecutionTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowExecutionTimeout;

    /**
     * The timeout for a single run of the child workflow execution. Each retry
     * or continue as new should obey this timeout.
     * Use {@see $workflowExecutionTimeout} to specify how long the parent is
     * willing to wait for the child completion.
     *
     * Optional: defaults to {@see $workflowExecutionTimeout}
     */
    #[Marshal(name: 'WorkflowRunTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowRunTimeout;

    /**
     * The workflow task timeout for the child workflow.
     *
     * Optional: default is no limit
     */
    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowTaskTimeout;

    /**
     * In case of a child workflow cancellation it fails with
     * a {@see FailedCancellationException}. The type defines at which point
     * the exception is thrown.
     *
     * @psalm-var ChildWorkflowCancellationEnum
     */
    #[Marshal(name: 'WaitForCancellation', type: ChildWorkflowCancellationType::class)]
    public int $cancellationType = ChildWorkflowCancellationType::TRY_CANCEL;

    /**
     * Whether server allow reuse of workflow ID, can be useful for dedup
     * logic if set to {@see IdReusePolicy::POLICY_REJECT_DUPLICATE}.
     */
    #[Marshal(name: 'WorkflowIDReusePolicy')]
    public int $workflowIdReusePolicy = IdReusePolicy::POLICY_ALLOW_DUPLICATE_FAILED_ONLY;

    /**
     * RetryPolicy specify how to retry child workflow if error happens.
     *
     * Optional: default is no retry.
     */
    #[Marshal(name: 'RetryPolicy', type: NullableType::class, of: RetryOptions::class)]
    public ?RetryOptions $retryOptions = null;

    /**
     * Optional cron schedule for workflow.
     *
     * @see CronSchedule::$interval for more info about cron format.
     */
    #[Marshal(name: 'CronSchedule', type: NullableType::class, of: CronType::class)]
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
     *
     * @psalm-var array<string, mixed>|null
     */
    #[Marshal(name: 'Memo', type: NullableType::class, of: ArrayType::class)]
    public ?array $memo = null;

    /**
     * Optional indexed info that can be used in query of List/Scan/Count
     * workflow APIs (only supported when Temporal server is using
     * ElasticSearch). The key and value type must be registered on Temporal
     * server side.
     *
     * @psalm-var array<string, mixed>|null
     */
    #[Marshal(name: 'SearchAttributes', type: NullableType::class, of: ArrayType::class)]
    public ?array $searchAttributes = null;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->workflowExecutionTimeout = CarbonInterval::seconds(0);
        $this->workflowRunTimeout = CarbonInterval::seconds(0);
        $this->workflowTaskTimeout = CarbonInterval::seconds(0);

        parent::__construct();
    }

    /**
     * @param MethodRetry|null $retry
     * @param CronSchedule|null $cron
     * @return $this
     */
    public function mergeWith(MethodRetry $retry = null, CronSchedule $cron = null): self
    {
        $self = clone $this;

        if ($retry !== null && $self->diff->isPresent($self, 'retryOptions')) {
            $self->retryOptions = $self->retryOptions->mergeWith($retry);
        }

        if ($cron !== null && $self->diff->isPresent($self, 'cronSchedule')) {
            $self->cronSchedule = $cron->interval;
        }

        return $self;
    }

    /**
     * Specify namespace in which workflow should be started.
     *
     * @param string $namespace
     * @return $this
     */
    #[Pure]
    public function withNamespace(string $namespace): self
    {
        $self = clone $this;

        $self->namespace = $namespace;

        return $self;
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
    #[Pure]
    public function withWorkflowId(string $workflowId): self
    {
        $self = clone $this;

        $self->workflowId = $workflowId;

        return $self;
    }

    /**
     * Task queue to use for workflow tasks. It should match a task queue
     * specified when creating a {@see Worker} that hosts the workflow code.
     *
     * @param string $taskQueue
     * @return $this
     */
    #[Pure]
    public function withTaskQueue(string $taskQueue): self
    {
        $self = clone $this;

        $self->taskQueue = $taskQueue;

        return $self;
    }

    /**
     * The maximum time that parent workflow is willing to wait for a child
     * execution (which includes retries and continue as new calls). If exceeded
     * the child is automatically terminated by the Temporal service.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withWorkflowExecutionTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->workflowExecutionTimeout = $timeout;
        return $self;
    }

    /**
     * The time after which workflow run is automatically terminated by the
     * Temporal service. Do not rely on the run timeout for business level
     * timeouts. It is preferred to use in workflow timers for this purpose.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withWorkflowRunTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->workflowRunTimeout = $timeout;
        return $self;
    }

    /**
     * Maximum execution time of a single workflow task. Default is 10 seconds.
     * Maximum accepted value is 60 seconds.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withWorkflowTaskTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0 && $timeout->totalSeconds <= 60);

        $self = clone $this;
        $self->workflowTaskTimeout = $timeout;
        return $self;
    }

    /**
     * In case of a child workflow cancellation it fails with
     * a {@see FailedCancellationException}. The type defines at which point
     * the exception is thrown.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param ChildWorkflowCancellationEnum $type
     * @return $this
     */
    #[Pure]
    public function withChildWorkflowCancellationType(int $type): self
    {
        assert(Assert::enum($type, ChildWorkflowCancellationType::class));

        $self = clone $this;
        $self->cancellationType = $type;
        return $self;
    }

    /**
     * Specifies server behavior if a completed workflow with the same id
     * exists. Note that under no conditions Temporal allows two workflows
     * with the same namespace and workflow id run simultaneously.
     *
     * - {@see IdReusePolicy::AllowDuplicateFailedOnly}: Is a default
     *  value. It means that workflow can start if previous run failed or was
     *  canceled or terminated.
     *
     * - {@see IdReusePolicy::AllowDuplicate}: Allows new run
     *  independently of the previous run closure status.
     *
     * - {@see IdReusePolicy::RejectDuplicate}: Doesn't allow new run
     *  independently of the previous run closure status.
     *
     * @return $this
     * @psalm-suppress ImpureMethodCall
     */
    #[Pure]
    public function withWorkflowIdReusePolicy(IdReusePolicy|int $policy,): self
    {
        \is_int($policy) and $policy = IdReusePolicy::from($policy);

        $self = clone $this;
        $self->workflowIdReusePolicy = $policy->value;
        return $self;
    }

    /**
     * RetryOptions that define how child workflow is retried in case of
     * failure. Default is null which is no reties.
     *
     * @param RetryOptions|null $options
     * @return $this
     */
    #[Pure]
    public function withRetryOptions(?RetryOptions $options): self
    {
        $self = clone $this;

        $self->retryOptions = $options;

        return $self;
    }

    /**
     * @see CronSchedule::$interval for more info about cron format.
     *
     * @param string|null $expression
     * @return $this
     */
    #[Pure]
    public function withCronSchedule(?string $expression): self
    {
        $self = clone $this;

        $self->cronSchedule = $expression;

        return $self;
    }

    /**
     * Specifies additional non-indexed information in result of list workflow.
     *
     * @param array|null $memo
     * @return $this
     */
    public function withMemo(?array $memo): self
    {
        $self = clone $this;

        $self->memo = $memo;

        return $self;
    }

    /**
     * Specifies additional indexed information in result of list workflow.
     *
     * @param array|null $searchAttributes
     * @return $this
     */
    public function withSearchAttributes(?array $searchAttributes): self
    {
        $self = clone $this;

        $self->searchAttributes = $searchAttributes;

        return $self;
    }

    /**
     * Specifies how this workflow reacts to the death of the parent workflow.
     *
     * @psalm-suppress ImpureMethodCall
     * @psalm-type ParentClosePolicyType = ParentClosePolicy::POLICY_*
     *
     * @param ParentClosePolicyType $policy
     * @return $this
     */
    public function withParentClosePolicy(
        #[ExpectedValues(valuesFromClass: ParentClosePolicy::class)]
        int $policy
    ): self {
        assert(Assert::enum($policy, ParentClosePolicy::class));

        $self = clone $this;

        $self->parentClosePolicy = $policy;

        return $self;
    }
}
