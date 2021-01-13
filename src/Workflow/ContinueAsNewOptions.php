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
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\ArrayType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Worker\FactoryInterface;
use Temporal\Worker\TaskQueue;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 */
final class ContinueAsNewOptions
{
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
     * TaskQueue that the child workflow needs to be scheduled on.
     *
     * Optional: the parent workflow task queue will be used if this is not
     * provided.
     */
    #[Marshal(name: 'TaskQueue')]
    public string $taskQueue = FactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * The workflow task timeout for the child workflow.
     *
     * Optional: default is 10s if this is not provided (or if 0 is provided).
     */
    #[Marshal(name: 'WorkflowTaskTimeout', type: DateIntervalType::class)]
    public \DateInterval $workflowTaskTimeout;

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
        $this->workflowRunTimeout = CarbonInterval::years(10);
        $this->workflowTaskTimeout = CarbonInterval::seconds(10);
    }

    /**
     * @return static
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Task queue to use for workflow tasks. It should match a task queue
     * specified when creating a {@see TaskQueue} that hosts the
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
}
