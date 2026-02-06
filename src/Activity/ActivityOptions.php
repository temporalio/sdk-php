<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Activity;

use JetBrains\PhpStorm\Pure;
use Spiral\Attributes\NamedArgumentConstructor;
use Temporal\Common\MethodRetry;
use Temporal\Common\Priority;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\ActivityCancellationType as ActivityCancellationMarshallerType;
use Temporal\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Internal\Marshaller\Type\NullableType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;

/**
 * ActivityOptions stores all activity-specific parameters that will be stored
 * inside of a context.
 *
 * The current timeout resolution implementation is in seconds and uses `ceil($interval->s)` as the duration.
 * But is subjected to change in the future.
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({ "METHOD", "CLASS" })
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD), NamedArgumentConstructor]
class ActivityOptions extends Options implements ActivityOptionsInterface
{
    /**
     * TaskQueue that the activity needs to be scheduled on.
     *
     * Optional: The default task queue with the same name as the workflow task
     * queue.
     */
    #[Marshal(name: 'TaskQueueName')]
    public ?string $taskQueue = null;

    /**
     * The end to end timeout for the activity needed. The zero value of this
     * uses default value.
     *
     * Optional: The default value is the sum of {@see $scheduleToStartTimeout}
     * and {@see $startToCloseTimeout}.
     */
    #[Marshal(name: 'ScheduleToCloseTimeout', type: DateIntervalType::class, nullable: true)]
    public ?\DateInterval $scheduleToCloseTimeout = null;

    /**
     * The queue timeout before the activity starts executed.
     */
    #[Marshal(name: 'ScheduleToStartTimeout', type: DateIntervalType::class, nullable: true)]
    public ?\DateInterval $scheduleToStartTimeout = null;

    /**
     * The timeout from the start of execution to end of it.
     */
    #[Marshal(name: 'StartToCloseTimeout', type: DateIntervalType::class, nullable: true)]
    public ?\DateInterval $startToCloseTimeout = null;

    /**
     * The periodic timeout while the activity is in execution.
     * This is the max interval the server needs to hear at-least one ping from the activity.
     */
    #[Marshal(name: 'HeartbeatTimeout', type: DateIntervalType::class, nullable: true)]
    public ?\DateInterval $heartbeatTimeout = null;

    /**
     * Whether to wait for canceled activity to be completed(activity can be
     * failed, completed, cancel accepted).
     *
     * @psalm-var int<0, 2>
     * @see ActivityCancellationType
     */
    #[Marshal(name: 'WaitForCancellation', type: ActivityCancellationMarshallerType::class)]
    public int $cancellationType = ActivityCancellationType::TRY_CANCEL;

    /**
     * Business level activity ID, this is not needed for most of the cases if
     * you have to specify this then talk to temporal team.
     *
     * This is something will be done in the future.
     */
    #[Marshal(name: 'ActivityID')]
    public string $activityId = '';

    /**
     * RetryPolicy specifies how to retry an Activity if an error occurs.
     *
     * More details are available at {@link https://docs.temporal.io/docs/concepts/activities}. RetryPolicy
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
     * Optional priority settings that control relative ordering of task processing when tasks are
     * backed up in a queue.
     *
     * Defaults to inheriting priority from the workflow that scheduled the activity.
     */
    #[Marshal(name: 'Priority')]
    public ?Priority $priority = null;

    /**
     * Optional summary of the activity.
     *
     * Single-line fixed summary for this activity that will appear in UI/CLI.
     * This can be in single-line Temporal Markdown format.
     *
     * @experimental This API is experimental and may change in the future.
     *
     * @since RoadRunner 2025.1.2
     */
    #[Marshal(name: 'Summary')]
    public string $summary = '';

    /**
     * @param DateIntervalValue|null $scheduleToCloseTimeout
     * @param DateIntervalValue|null $scheduleToStartTimeout
     * @param DateIntervalValue|null $startToCloseTimeout
     * @param DateIntervalValue|null $heartbeatTimeout
     */
    public function __construct(
        ?string $taskQueue = null,
        $scheduleToCloseTimeout = null,
        $scheduleToStartTimeout = null,
        $startToCloseTimeout = null,
        $heartbeatTimeout = null,
        ActivityCancellationType|int $cancellationType = ActivityCancellationType::TRY_CANCEL,
        string $activityId = '',
        ?RetryOptions $retryOptions = null,
        ?Priority $priority = null,
        string $summary = '',
    ) {
        parent::__construct();

        $this->taskQueue = $taskQueue;
        $this->scheduleToCloseTimeout = DateInterval::parseOrNull($scheduleToCloseTimeout, DateInterval::FORMAT_SECONDS);
        $this->scheduleToStartTimeout = DateInterval::parseOrNull($scheduleToStartTimeout, DateInterval::FORMAT_SECONDS);
        $this->startToCloseTimeout = DateInterval::parseOrNull($startToCloseTimeout, DateInterval::FORMAT_SECONDS);
        $this->heartbeatTimeout = DateInterval::parseOrNull($heartbeatTimeout, DateInterval::FORMAT_SECONDS);
        $this->cancellationType = $cancellationType instanceof ActivityCancellationType
            ? $cancellationType->value
            : (\is_int($cancellationType) ? $cancellationType : ActivityCancellationType::TRY_CANCEL);
        $this->activityId = $activityId;
        $this->retryOptions = $retryOptions;
        $this->priority = $priority ?? Priority::new();
        $this->summary = $summary;
    }

    /**
     * @return $this
     */
    public function mergeWith(?MethodRetry $retry = null): self
    {
        $self = clone $this;

        if ($retry !== null) {
            $self->retryOptions = ($self->retryOptions ?? RetryOptions::new())->mergeWith($retry);
        }

        return $self;
    }

    /**
     * @return $this
     */
    public function mergeWithOptions(?ActivityOptionsInterface $options = null): self
    {
        if ($options === null) {
            return $this;
        }

        $self = clone $this;

        foreach ($options->diff->getChangedPropertyNames($options) as $name) {
            if ($name === 'retryOptions') {
                $self->retryOptions = ($self->retryOptions ?? RetryOptions::new())->mergeWith($options->retryOptions);
                continue;
            }

            $self->$name = $options->$name;
        }

        return $self;
    }

    /**
     * Task queue to use when dispatching activity task to a worker.
     *
     * By default, it is the same task list name the workflow was started with.
     *
     * @return $this
     */
    #[Pure]
    public function withTaskQueue(?string $taskQueue): self
    {
        $self = clone $this;

        $self->taskQueue = $taskQueue;

        return $self;
    }

    /**
     * Overall timeout workflow is willing to wait for activity to complete.
     *
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
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->scheduleToCloseTimeout = $timeout;
        return $self;
    }

    /**
     * Time activity can stay in task queue before it is picked up by a worker.
     *
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
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->scheduleToStartTimeout = $timeout;
        return $self;
    }

    /**
     * Maximum activity execution time after it was sent to a worker.
     *
     * If schedule to close is not provided then both this and schedule to start are required.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withStartToCloseTimeout($timeout): self
    {
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->startToCloseTimeout = $timeout;
        return $self;
    }

    /**
     * Heartbeat interval.
     *
     * Activity must heartbeat before this interval passes after a last heartbeat or activity start.
     *
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     * @return $this
     */
    #[Pure]
    public function withHeartbeatTimeout($timeout): self
    {
        \assert(DateInterval::assert($timeout));
        $timeout = DateInterval::parse($timeout, DateInterval::FORMAT_SECONDS);
        \assert($timeout->totalMicroseconds >= 0);

        $self = clone $this;
        $self->heartbeatTimeout = $timeout;
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @return $this
     */
    #[Pure]
    public function withCancellationType(ActivityCancellationType|int $type): self
    {
        \is_int($type) and $type = ActivityCancellationType::from($type);

        $self = clone $this;
        $self->cancellationType = $type->value;
        return $self;
    }

    /**
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
     * Optional priority settings that control relative ordering of task processing when tasks are
     * backed up in a queue.
     *
     * Defaults to inheriting priority from the workflow that scheduled the activity.
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

    /**
     * Optional summary of the activity.
     *
     * Single-line fixed summary for this activity that will appear in UI/CLI.
     * This can be in single-line Temporal Markdown format.
     *
     * @experimental This API is experimental and may change in the future.
     *
     * @return $this
     */
    #[Pure]
    public function withSummary(string $summary): self
    {
        $self = clone $this;
        $self->summary = $summary;
        return $self;
    }
}
