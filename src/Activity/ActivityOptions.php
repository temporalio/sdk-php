<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Carbon\CarbonInterval;
use Temporal\Client\Common\RetryOptions;
use Temporal\Client\Internal\Support\DataTransferObject;
use Temporal\Client\Internal\Support\DateInterval;

/**
 * ActivityOptions stores all activity-specific parameters that will be stored
 * inside of a context. The current timeout resolution implementation is in
 * seconds and uses `ceil($interval->s)` as the duration. But is subjected to
 * change in the future.
 *
 * @psalm-import-type DateIntervalFormat from DateInterval
 * @psalm-import-type RetryOptionsArray from RetryOptions
 *
 * @psalm-type ActivityOptionsArray = {
 *      taskQueue: string|null,
 *      scheduleToCloseTimeout: DateIntervalFormat|null,
 *      scheduleToStartTimeout: DateIntervalFormat|null,
 *      startToCloseTimeout: DateIntervalFormat|null,
 *      heartbeatTimeout: DateIntervalFormat|null,
 *      waitForCancellation: bool,
 *      activityId: string,
 *      retryOptions: RetryOptionsArray|RetryOptions,
 * }
 */
final class ActivityOptions extends DataTransferObject
{
    /**
     * TaskQueue that the activity needs to be scheduled on.
     *
     * Optional: The default task queue with the same name as the workflow task
     * queue.
     */
    protected ?string $taskQueue = null;

    /**
     * The end to end timeout for the activity needed. The zero value of this
     * uses default value.
     *
     * Optional: The default value is the sum of {@see ScheduleToStartTimeout} and
     * StartToCloseTimeout
     */
    public ?\DateInterval $scheduleToCloseTimeout = null;

    /**
     * The queue timeout before the activity starts executed.
     */
    public ?\DateInterval $scheduleToStartTimeout = null;

    /**
     * The timeout from the start of execution to end of it.
     */
    public ?\DateInterval $startToCloseTimeout = null;

    /**
     * The periodic timeout while the activity is in execution. This is the max
     * interval the server needs to hear at-least one ping from the activity.
     */
    public ?\DateInterval $heartbeatTimeout = null;

    /**
     * Whether to wait for canceled activity to be completed(activity can be
     * failed, completed, cancel accepted).
     */
    public bool $waitForCancellation = false;

    /**
     * Business level activity ID, this is not needed for most of the cases if
     * you have to specify this then talk to temporal team. This is something
     * will be done in future.
     */
    public string $activityId = '';

    /**
     * RetryPolicy specifies how to retry an Activity if an error occurs. More
     * details are available at {@link https://docs.temporal.io}. RetryPolicy
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
    public RetryOptions $retryOptions;

    /**
     * ActivityOptions constructor.
     */
    public function __construct()
    {
        $this->retryOptions = new RetryOptions();
    }

    /**
     * @return string|null
     */
    public function getTaskQueue(): ?string
    {
        return $this->taskQueue;
    }

    /**
     * @param string|null $taskQueue
     * @return $this
     */
    public function setTaskQueue(?string $taskQueue): self
    {
        $this->taskQueue = $taskQueue;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getScheduleToCloseTimeout(): ?int
    {
        if ($this->scheduleToCloseTimeout === null) {
            return null;
        }

        return CarbonInterval::make($this->scheduleToCloseTimeout)->milliseconds;
    }

    /**
     * @param DateIntervalFormat|null $timeout
     * @return $this
     * @throws \Exception
     */
    public function setScheduleToCloseTimeout($timeout): self
    {
        assert($timeout === null || DateInterval::assert($timeout), 'Precondition failed');

        $this->scheduleToCloseTimeout = $timeout !== null ? DateInterval::parse($timeout) : null;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getScheduleToStartTimeout(): ?int
    {
        if ($this->scheduleToStartTimeout === null) {
            return null;
        }

        return CarbonInterval::make($this->scheduleToStartTimeout)->milliseconds;
    }

    /**
     * @param DateIntervalFormat|null $timeout
     * @return $this
     * @throws \Exception
     */
    public function setScheduleToStartTimeout($timeout): self
    {
        assert($timeout === null || DateInterval::assert($timeout), 'Precondition failed');

        $this->scheduleToStartTimeout = $timeout !== null ? DateInterval::parse($timeout) : null;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getStartToCloseTimeout(): ?int
    {
        if ($this->startToCloseTimeout === null) {
            return null;
        }

        return CarbonInterval::make($this->startToCloseTimeout)->milliseconds;
    }

    /**
     * @param DateIntervalFormat|null $timeout
     * @return $this
     * @throws \Exception
     */
    public function setStartToCloseTimeout($timeout): self
    {
        assert($timeout === null || DateInterval::assert($timeout), 'Precondition failed');

        $this->startToCloseTimeout = $timeout !== null ? DateInterval::parse($timeout) : null;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getHeartbeatTimeout(): ?int
    {
        if ($this->heartbeatTimeout === null) {
            return null;
        }

        return CarbonInterval::make($this->heartbeatTimeout)->milliseconds;
    }

    /**
     * @param DateIntervalFormat|null $timeout
     * @return $this
     * @throws \Exception
     */
    public function setHeartbeatTimeout($timeout): self
    {
        assert($timeout === null || DateInterval::assert($timeout), 'Precondition failed');

        $this->heartbeatTimeout = $timeout !== null ? DateInterval::parse($timeout) : null;

        return $this;
    }

    /**
     * @return bool
     */
    public function isWaitForCancellation(): bool
    {
        return $this->waitForCancellation;
    }

    /**
     * @param bool $waitForCancellation
     * @return $this
     */
    public function setWaitForCancellation(bool $waitForCancellation = false): self
    {
        $this->waitForCancellation = $waitForCancellation;

        return $this;
    }

    /**
     * @return string
     */
    public function getActivityId(): string
    {
        return $this->activityId;
    }

    /**
     * @param string $activityId
     * @return $this
     */
    public function setActivityId(string $activityId): self
    {
        $this->activityId = $activityId;

        return $this;
    }

    /**
     * @return RetryOptions
     */
    public function getRetryOptions(): RetryOptions
    {
        return $this->retryOptions;
    }

    /**
     * @param RetryOptions|RetryOptionsArray $options
     * @return $this
     */
    public function setRetryOptions($options): self
    {
        $this->retryOptions = RetryOptions::new($options);

        return $this;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->arrayKeysToUpper(parent::toArray());
    }
}
