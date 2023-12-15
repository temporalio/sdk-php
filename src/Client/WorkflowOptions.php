<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client;

use Carbon\CarbonInterval;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Common\CronSchedule;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\MethodRetry;
use Temporal\Common\RetryOptions;
use Temporal\Common\Uuid;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\CronType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\Worker;

/**
 * WorkflowOptions configuration parameters for starting a workflow execution.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 * @psalm-immutable
 */
final class WorkflowOptions extends Options
{
    /**
     * The business identifier of the workflow execution.
     */
    #[Marshal(name: 'WorkflowID')]
    public string $workflowId;

    /**
     * The workflow tasks of the workflow are scheduled on the queue with this
     * name. This is also the name of the activity task queue on which
     * activities are scheduled.
     *
     * The workflow author can choose to override this using activity options.
     */
    #[Marshal(name: 'TaskQueue')]
    public string $taskQueue = WorkerFactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * Eager Workflow Dispatch is a mechanism that minimizes the duration from starting a workflow to the
     * processing of the first workflow task, making Temporal more suitable for latency sensitive applications.
     */
    #[Marshal(name: 'EnableEagerStart')]
    public bool $eagerStart = false;

    /**
     * The timeout for duration of workflow execution.
     *
     * It includes retries and continue as new. Use {@see $workflowRunTimeout}
     * to limit execution time of a single workflow run.
     *
     * Optional: defaulted to 10 years.
     */
    #[Marshal(name: 'WorkflowExecutionTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowExecutionTimeout;

    /**
     * The timeout for duration of a single workflow run.
     *
     * Optional: defaulted to {@see $workflowExecutionTimeout}.
     */
    #[Marshal(name: 'WorkflowRunTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowRunTimeout;

    /**
     * Time to wait before dispatching the first Workflow task.
     */
    #[Marshal(name: 'WorkflowStartDelay', type: DateIntervalType::class)]
    public \DateInterval $workflowStartDelay;

    /**
     * The timeout for processing workflow task from the time the worker pulled
     * this task. If a workflow task is lost, it is retried after this timeout.
     *
     * Optional: defaulted to no limit
     */
    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowTaskTimeout;

    /**
     * Whether server allow reuse of workflow ID, can be useful for dedup logic
     * if set to {@see IdReusePolicy::POLICY_REJECT_DUPLICATE}.
     */
    #[Marshal(name: 'WorkflowIDReusePolicy')]
    public int $workflowIdReusePolicy = IdReusePolicy::POLICY_ALLOW_DUPLICATE_FAILED_ONLY;

    /**
     * Optional retry policy for workflow. If a retry policy is specified, in
     * case of workflow failure server will start new workflow execution if
     * needed based on the retry policy.
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
        $this->workflowId = Uuid::v4();
        $this->workflowExecutionTimeout = CarbonInterval::seconds(0);
        $this->workflowRunTimeout = CarbonInterval::seconds(0);
        $this->workflowTaskTimeout = CarbonInterval::seconds(0);
        $this->workflowStartDelay = CarbonInterval::seconds(0);

        parent::__construct();
    }

    /**
     * @param MethodRetry|null $retry
     * @param CronSchedule|null $cron
     *
     * @return self return a new {@see self} instance with merged options
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
     * specified when creating a {@see Worker} that hosts the
     * workflow code.
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
     * Eager Workflow Dispatch is a mechanism that minimizes the duration from starting a workflow to the
     * processing of the first workflow task, making Temporal more suitable for latency sensitive applications.
     *
     * Eager Workflow Dispatch can be enabled if the server supports it and a local worker
     * is available the task is fed directly to the worker.
     *
     * @param bool $value
     * @return $this
     */
    #[Pure]
    public function withEagerStart(bool $value = true): self
    {
        $self = clone $this;

        $self->eagerStart = $value;

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
     * Time to wait before dispatching the first Workflow task.
     * If the Workflow gets a Signal before the delay, a Workflow task will be dispatched and the rest
     * of the delay will be ignored. A Signal from {@see WorkflowClientInterface::startWithSignal()} won't
     * trigger a workflow task. Cannot be set the same time as a {@see $cronSchedule}.
     *
     * NOTE: Experimental
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $delay
     * @return $this
     */
    #[Pure]
    public function withWorkflowStartDelay($delay): self
    {
        assert(DateInterval::assert($delay));
        $delay = DateInterval::parse($delay, DateInterval::FORMAT_SECONDS);

        $self = clone $this;
        $self->workflowStartDelay = $delay;
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
     * @psalm-suppress ImpureMethodCall
     *
     * @return $this
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
    #[Pure]
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
    #[Pure]
    public function withSearchAttributes(?array $searchAttributes): self
    {
        $self = clone $this;

        $self->searchAttributes = $searchAttributes;

        return $self;
    }

    /**
     * @param DataConverterInterface $converter
     * @return Memo|null
     * @internal
     */
    public function toMemo(DataConverterInterface $converter): ?Memo
    {
        if ($this->memo === null) {
            return null;
        }

        $fields = [];

        foreach ($this->memo as $key => $value) {
            $fields[$key] = $converter->toPayload($value);
        }

        $memo = new Memo();
        $memo->setFields($fields);

        return $memo;
    }

    /**
     * @param DataConverterInterface $converter
     * @return SearchAttributes|null
     * @internal
     */
    public function toSearchAttributes(DataConverterInterface $converter): ?SearchAttributes
    {
        if ($this->searchAttributes === null) {
            return null;
        }

        $fields = [];
        foreach ($this->searchAttributes as $key => $value) {
            $fields[$key] = $converter->toPayload($value);
        }

        $search = new SearchAttributes();
        $search->setIndexedFields($fields);

        return $search;
    }
}
