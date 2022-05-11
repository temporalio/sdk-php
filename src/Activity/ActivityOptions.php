<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use Carbon\CarbonInterval;
use JetBrains\PhpStorm\Pure;
use Temporal\Common\MethodRetry;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Assert;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;
use Temporal\Worker\WorkerFactoryInterface;

/**
 * ActivityOptions stores all activity-specific parameters that will be stored
 * inside of a context. The current timeout resolution implementation is in
 * seconds and uses `ceil($interval->s)` as the duration. But is subjected to
 * change in the future.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 */
class ActivityOptions extends Options implements ActivityOptionsInterface
{
    /**
     * TaskQueue that the activity needs to be scheduled on.
     *
     * Optional: The default task queue with the same name as the workflow task
     * queue.
     */
    #[Marshal(name: 'TaskQueueName')]
    public string $taskQueue = WorkerFactoryInterface::DEFAULT_TASK_QUEUE;

    /**
     * The end to end timeout for the activity needed. The zero value of this
     * uses default value.
     *
     * Optional: The default value is the sum of {@see $scheduleToStartTimeout}
     * and {@see $startToCloseTimeout}.
     */
    #[Marshal(name: 'ScheduleToCloseTimeout', type: DateIntervalType::class)]
    public \DateInterval $scheduleToCloseTimeout;

    /**
     * The queue timeout before the activity starts executed.
     */
    #[Marshal(name: 'ScheduleToStartTimeout', type: DateIntervalType::class)]
    public \DateInterval $scheduleToStartTimeout;

    /**
     * The timeout from the start of execution to end of it.
     */
    #[Marshal(name: 'StartToCloseTimeout', type: DateIntervalType::class)]
    public \DateInterval $startToCloseTimeout;

    /**
     * The periodic timeout while the activity is in execution. This is the max
     * interval the server needs to hear at-least one ping from the activity.
     */
    #[Marshal(name: 'HeartbeatTimeout', type: DateIntervalType::class)]
    public \DateInterval $heartbeatTimeout;

    /**
     * Whether to wait for canceled activity to be completed(activity can be
     * failed, completed, cancel accepted).
     *
     * @psalm-var ActivityCancellationType::*
     */
    #[Marshal(name: 'WaitForCancellation', type: ActivityCancellationType::class)]
    public int $cancellationType = ActivityCancellationType::TRY_CANCEL;

    /**
     * Business level activity ID, this is not needed for most of the cases if
     * you have to specify this then talk to temporal team. This is something
     * will be done in future.
     */
    #[Marshal(name: 'ActivityID')]
    public string $activityId = '';

    /**
     * RetryPolicy specifies how to retry an Activity if an error occurs. More
     * details are available at {@link https://docs.temporal.io/docs/concepts/activities}. RetryPolicy
     * is optional. If one is not specified a default RetryPolicy is provided
     * by the server.
     *
     * The default RetryPolicy provided by the server specifies:
     * - InitialInterval of 1 second
     * - BackoffCoefficient of 2.0
     * - MaximumInterval of 100 x InitialInterval
     * - MaximumAttempts of 0 (unlimited)
     *
     * To disable retries set MaximumAttempts to 1. The default RetryPolicy
     * provided by the server can be overridden by the dynamic config.
     */
    #[Marshal(name: 'RetryPolicy', type: NullableType::class, of: RetryOptions::class)]
    public ?RetryOptions $retryOptions = null;

    /**
     * ActivityOptions constructor.
     */
    public function __construct()
    {
        $this->scheduleToStartTimeout = CarbonInterval::seconds(0);
        $this->scheduleToCloseTimeout = CarbonInterval::seconds(0);
        $this->startToCloseTimeout = CarbonInterval::seconds(0);
        $this->heartbeatTimeout = CarbonInterval::seconds(0);

        parent::__construct();
    }

    /**
     * @param MethodRetry|null $retry
     * @return $this
     */
    public function mergeWith(MethodRetry $retry = null): self
    {
        $self = clone $this;

        if ($retry !== null && $this->diff->isPresent($self, 'retryOptions')) {
            $self->retryOptions = $this->retryOptions->mergeWith($retry);
        }

        return $self;
    }

    /**
     * Task queue to use when dispatching activity task to a worker. By default
     * it is the same task list name the workflow was started with.
     *
     * @param string|null $taskQueue
     * @return $this
     */
    #[Pure]
    public function withTaskQueue(?string $taskQueue): self
    {
        $self = clone $this;

        $self->taskQueue = $taskQueue ?? WorkerFactoryInterface::DEFAULT_TASK_QUEUE;

        return $self;
    }

    /**
     * Overall timeout workflow is willing to wait for activity to complete.
     * It includes time in a task queue:
     *
     * - Use {@see ActivityOptions::withScheduleToStartTimeout($timeout)} to limit it.
     *
     * Plus activity execution time:
     *
     * - Use {@see ActivityOptions::withStartToCloseTimeout($timeout)} to limit it.
     *
     * Either this option or both schedule to start and start to close are
     * required.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withScheduleToCloseTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->scheduleToCloseTimeout = $timeout;
        return $self;
    }

    /**
     * Time activity can stay in task queue before it is picked up by a worker.
     * If schedule to close is not provided then both this and start to close
     * are required.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withScheduleToStartTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->scheduleToStartTimeout = $timeout;
        return $self;
    }

    /**
     * Maximum activity execution time after it was sent to a worker. If
     * schedule to close is not provided then both this and schedule to start
     * are required.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withStartToCloseTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->startToCloseTimeout = $timeout;
        return $self;
    }

    /**
     * Heartbeat interval. Activity must heartbeat before this interval passes
     * after a last heartbeat or activity start.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withHeartbeatTimeout($timeout): self
    {
        assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->heartbeatTimeout = $timeout;
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param int $type
     * @return $this
     */
    #[Pure]
    public function withCancellationType(int $type): self
    {
        assert(Assert::enum($type, ActivityCancellationType::class));

        $self = clone $this;
        $self->cancellationType = $type;
        return $self;
    }

    /**
     * @param string $activityId
     * @return $this
     */
    #[Pure]
    public function withActivityId(string $activityId): self
    {
        $self = clone $this;

        $self->activityId = $activityId;

        return $self;
    }

    /**
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
}
