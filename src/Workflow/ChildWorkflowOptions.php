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
use JetBrains\PhpStorm\Pure;
use Temporal\Client\ClientOptions;
use Temporal\Common\CronSchedule;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\MethodRetry;
use Temporal\Common\Priority;
use Temporal\Common\RetryOptions;
use Temporal\Exception\FailedCancellationException;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Meta\MarshalAssocArray;
use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\ChildWorkflowCancellationType as ChildWorkflowCancellationMarshalType;
use Temporal\Internal\Marshaller\Type\CronType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Workflow;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
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
     * In case of a child workflow cancellation it fails with a FailedCancellationException. The type defines at which
     * point the exception is thrown.
     *
     * @see FailedCancellationException
     *
     * @psalm-var int<0, 3>
     * @see ChildWorkflowCancellationType
     */
    #[Marshal(name: 'WaitForCancellation', type: ChildWorkflowCancellationMarshalType::class)]
    public int $cancellationType = ChildWorkflowCancellationType::TRY_CANCEL;

    /**
     * Whether server allow reuse of workflow ID, can be useful for deduplication
     * logic if set to IdReusePolicy::POLICY_REJECT_DUPLICATE.
     *
     * @see IdReusePolicy::RejectDuplicate
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
    #[MarshalAssocArray(name: 'SearchAttributes', nullable: true)]
    public ?array $searchAttributes = null;

    /**
     * General fixed details for this workflow execution that will appear in UI/CLI.
     *
     * @experimental This feature is not stable and may change in the future.
     */
    #[Marshal(name: 'StaticDetails')]
    public string $staticDetails = '';

    /**
     * Single-line fixed summary for this workflow execution that will appear in UI/CLI.
     *
     * @experimental This feature is not stable and may change in the future.
     */
    #[Marshal(name: 'StaticSummary')]
    public string $staticSummary = '';

    /**
     * Optional priority settings that control relative ordering of task processing when tasks are
     * backed up in a queue.
     */
    #[Marshal(name: 'Priority')]
    public Priority $priority;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->workflowExecutionTimeout = CarbonInterval::seconds(0);
        $this->workflowRunTimeout = CarbonInterval::seconds(0);
        $this->workflowTaskTimeout = CarbonInterval::seconds(0);

        // Inherit Namespace and TaskQueue from the current Workflow if possible
        try {
            $info = Workflow::getInfo();
            $this->namespace = $info->namespace;
            $this->taskQueue = $info->taskQueue;
            $this->priority = $info->priority;
        } catch (\Throwable) {
            $this->priority = Priority::new();
        }

        parent::__construct();
    }

    /**
     * @return $this
     */
    public function mergeWith(?MethodRetry $retry = null, ?CronSchedule $cron = null): self
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
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

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
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

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
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0 && $timeout->totalSeconds <= 60);

        $self = clone $this;
        $self->workflowTaskTimeout = $timeout;
        return $self;
    }

    /**
     * The type defines at which point the exception is thrown.
     *
     * In case of a child workflow cancellation it fails with a {@see FailedCancellationException}.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @return $this
     */
    #[Pure]
    public function withChildWorkflowCancellationType(ChildWorkflowCancellationType|int $type): self
    {
        \is_int($type) and $type = ChildWorkflowCancellationType::from($type);

        $self = clone $this;
        $self->cancellationType = $type->value;
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
    public function withWorkflowIdReusePolicy(IdReusePolicy|int $policy): self
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
     * Specifies optional cron schedule for workflow.
     *
     * @see CronSchedule::$interval for more info about cron format.
     *
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
     *
     * @return $this
     */
    public function withParentClosePolicy(ParentClosePolicy|int $policy): self
    {
        \is_int($policy) and $policy = ParentClosePolicy::from($policy);

        $self = clone $this;

        $self->parentClosePolicy = $policy->value;

        return $self;
    }

    /**
     * Single-line fixed summary for this workflow execution that will appear in UI/CLI.
     *
     * This can be in single-line Temporal Markdown format.
     *
     * @return $this
     * @since SDK 2.14.0
     * @experimental This API might change in the future.
     */
    #[Pure]
    public function withStaticSummary(string $summary): self
    {
        $self = clone $this;
        $self->staticSummary = $summary;
        return $self;
    }

    /**
     * General fixed details for this workflow execution that will appear in UI/CLI.
     *
     * This can be in Temporal Markdown format and can span multiple lines.
     * This is a fixed value on the workflow that cannot be updated.
     *
     * @return $this
     * @since SDK 2.14.0
     * @experimental This API might change in the future.
     */
    #[Pure]
    public function withStaticDetails(string $details): self
    {
        $self = clone $this;
        $self->staticDetails = $details;
        return $self;
    }

    /**
     * Optional priority settings that control relative ordering of task processing when tasks are
     * backed up in a queue.
     *
     * @return $this
     *
     * @internal Experimental
     */
    #[Pure]
    public function withPriority(Priority $priority): self
    {
        $self = clone $this;
        $self->priority = $priority;
        return $self;
    }
}
