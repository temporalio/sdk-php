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
use Cron\CronExpression;
use Temporal\Api\Common\V1\Memo;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Api\Common\V1\SearchAttributes;
use Temporal\Common\CronSchedule;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\MethodRetry;
use Temporal\Common\RetryOptions;
use Temporal\Common\Uuid;
use Temporal\Internal\Assert;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\CronType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Marshaller\Type\ObjectType;
use Temporal\Internal\Support\Cron;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\Worker;

/**
 * WorkflowOptions configuration parameters for starting a workflow execution.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 * @psalm-import-type IdReusePolicyEnum from IdReusePolicy
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
     * The timeout for processing workflow task from the time the worker pulled
     * this task. If a workflow task is lost, it is retried after this timeout.
     *
     * Optional: defaulted to 10 secs.
     */
    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowTaskTimeout;

    /**
     * Whether server allow reuse of workflow ID, can be useful for dedup logic
     * if set to {@see IdReusePolicy::POLICY_REJECT_DUPLICATE}.
     *
     * @psalm-var IdReusePolicyEnum
     */
    #[Marshal(name: 'WorkflowIDReusePolicy')]
    public int $workflowIdReusePolicy = IdReusePolicy::POLICY_ALLOW_DUPLICATE_FAILED_ONLY;

    /**
     * Optional retry policy for workflow. If a retry policy is specified, in
     * case of workflow failure server will start new workflow execution if
     * needed based on the retry policy.
     */
    #[Marshal(name: 'RetryPolicy', type: ObjectType::class, of: RetryOptions::class)]
    public RetryOptions $retryOptions;

    /**
     * Optional cron schedule for workflow.
     */
    #[Marshal(name: 'CronSchedule', type: NullableType::class, of: CronType::class)]
    public ?CronExpression $cronSchedule = null;

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
        $this->workflowExecutionTimeout = CarbonInterval::years(10);
        $this->workflowRunTimeout = CarbonInterval::years(10);
        $this->workflowTaskTimeout = CarbonInterval::seconds(10);
        $this->retryOptions = new RetryOptions();

        parent::__construct();
    }

    /**
     * @param MethodRetry|null $retry
     * @param CronSchedule|null $cron
     * @return $this
     */
    public function mergeWith(MethodRetry $retry = null, CronSchedule $cron = null): self
    {
        return immutable(function () use ($retry, $cron) {
            if ($retry !== null && $this->diff->isPresent($this, 'retryOptions')) {
                $this->retryOptions = $this->retryOptions->mergeWith($retry);
            }

            if ($cron !== null && $this->diff->isPresent($this, 'cronSchedule')) {
                if ($this->cronSchedule === null) {
                    $this->cronSchedule = clone $cron->interval;
                }

                $this->cronSchedule->setExpression($cron->interval->getExpression());
            }
        });
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
     * specified when creating a {@see Worker} that hosts the
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
     * @param IdReusePolicyEnum $policy
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
     * @param string|CronExpression|CronSchedule|null $expression
     * @return $this
     */
    public function withCronSchedule($expression): self
    {
        return immutable(fn() => $this->cronSchedule = Cron::parseOrNull($expression));
    }

    /**
     * Specifies additional non-indexed information in result of list workflow.
     *
     * @param array|null $memo
     * @return $this
     */
    public function withMemo(?array $memo): self
    {
        return immutable(fn() => $this->memo = $memo);
    }

    /**
     * Specifies additional indexed information in result of list workflow.
     *
     * @param array|null $searchAttributes
     * @return $this
     */
    public function withSearchAttributes(?array $searchAttributes): self
    {
        return immutable(fn() => $this->searchAttributes = $searchAttributes);
    }

    /**
     * @return Memo
     * @internal
     */
    public function toMemo(): Memo
    {
        $memo = new Memo();
        $memo->setFields($this->memo);

        return $memo;
    }

    /**
     * @return SearchAttributes
     * @internal
     */
    public function toSearchAttributes(): SearchAttributes
    {
        $search = new SearchAttributes();
        $search->setIndexedFields($this->searchAttributes);

        return $search;
    }

    /**
     * @return RetryPolicy
     * @internal
     */
    public function toRetryPolicy(): RetryPolicy
    {
        $rt = new RetryPolicy();
        $rt->setInitialInterval(DateInterval::toDuration($this->retryOptions->initialInterval));
        $rt->setMaximumInterval(DateInterval::toDuration($this->retryOptions->maximumInterval));

        $rt->setBackoffCoefficient($this->retryOptions->backoffCoefficient);
        $rt->setMaximumAttempts($this->retryOptions->maximumAttempts);
        $rt->setNonRetryableErrorTypes($this->retryOptions->nonRetryableExceptions);

        return $rt;
    }
}
