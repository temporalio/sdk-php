<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use Temporal\Client\Common\RetryOptions;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Client\Internal\Marshaller\Type\NullableType;
use Temporal\Client\Internal\Marshaller\Type\ObjectType;

/**
 * ActivityOptions stores all activity-specific parameters that will be stored
 * inside of a context. The current timeout resolution implementation is in
 * seconds and uses `ceil($interval->s)` as the duration. But is subjected to
 * change in the future.
 *
 * @psalm-import-type RetryOptionsArray from RetryOptions
 */
class ActivityOptions
{
    /**
     * TaskQueue that the activity needs to be scheduled on.
     *
     * Optional: The default task queue with the same name as the workflow task
     * queue.
     *
     * @var string|null
     */
    #[Marshal(name: 'TaskQueue')]
    public ?string $taskQueue = null;

    /**
     * The end to end timeout for the activity needed. The zero value of this
     * uses default value.
     *
     * Optional: The default value is the sum of {@see $scheduleToStartTimeout}
     * and {@see $startToCloseTimeout}.
     *
     * @var \DateInterval|null
     */
    #[Marshal(name: 'ScheduleToCloseTimeout', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $scheduleToCloseTimeout = null;

    /**
     * The queue timeout before the activity starts executed.
     *
     * @var \DateInterval|null
     */
    #[Marshal(name: 'ScheduleToStartTimeout', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $scheduleToStartTimeout = null;

    /**
     * The timeout from the start of execution to end of it.
     *
     * @var \DateInterval|null
     */
    #[Marshal(name: 'StartToCloseTimeout', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $startToCloseTimeout = null;

    /**
     * The periodic timeout while the activity is in execution. This is the max
     * interval the server needs to hear at-least one ping from the activity.
     *
     * @var \DateInterval|null
     */
    #[Marshal(name: 'HeartbeatTimeout', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $heartbeatTimeout = null;

    /**
     * Whether to wait for canceled activity to be completed(activity can be
     * failed, completed, cancel accepted).
     *
     * @var bool
     */
    #[Marshal(name: 'WaitForCancellation')]
    public bool $waitForCancellation = false;

    /**
     * Business level activity ID, this is not needed for most of the cases if
     * you have to specify this then talk to temporal team. This is something
     * will be done in future.
     *
     * @var string
     */
    #[Marshal(name: 'ActivityID')]
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
     *
     * @var RetryOptions
     */
    #[Marshal(name: 'RetryPolicy', type: ObjectType::class, of: RetryOptions::class)]
    public RetryOptions $retryOptions;

    /**
     * ActivityOptions constructor.
     */
    public function __construct()
    {
        $this->retryOptions = new RetryOptions();
    }

    /**
     * @return static
     */
    public static function new(): self
    {
        return new static();
    }
}
