<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Common;

use Carbon\CarbonInterval;
use Temporal\Client\Activity\ActivityOptions;
use Temporal\Client\Internal\Marshaller\Meta\Marshal;
use Temporal\Client\Internal\Marshaller\Type\DateIntervalType;
use Temporal\Client\Internal\Marshaller\Type\NullableType;
use Temporal\Client\Internal\Support\DateInterval;
use Temporal\Client\Internal\Support\Iter;

/**
 * Note that the history of activity with retry policy will be different:
 *
 * The started event will be written down into history only when the activity
 * completes or "finally" timeouts/fails. And the started event only records
 * the last started time. Because of that, to check an activity has started or
 * not, you cannot rely on history events. Instead, you can use CLI to describe
 * the workflow to see the status of the activity:
 *     temporal --do <namespace> wf desc -w <wf-id>
 *
 * @psalm-import-type DateIntervalFormat from DateInterval
 */
class RetryOptions
{
    /**
     * Backoff interval for the first retry. If {@see RetryOptions::$backoffCoefficient}
     * is 1.0 then it is used for all retries.
     *
     * @var \DateInterval|null
     */
    #[Marshal(name: 'InitialInterval', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $initialInterval = null;

    /**
     * Coefficient used to calculate the next retry backoff interval. The next
     * retry interval is previous interval multiplied by this coefficient.
     *
     * Note: Must be greater than 1.0
     *
     * @var float
     */
    #[Marshal(name: 'BackoffCoefficient')]
    public float $backoffCoefficient = 2.0;

    /**
     * Maximum backoff interval between retries. Exponential backoff leads to
     * interval increase. This value is the cap of the interval.
     *
     * Default is 100x of initial interval.
     *
     * @var \DateInterval|null
     */
    #[Marshal(name: 'MaximumInterval', type: NullableType::class, of: DateIntervalType::class)]
    public ?\DateInterval $maximumInterval = null;

    /**
     * Maximum number of attempts. When exceeded the retries stop even if not
     * expired yet. If not set or set to 0, it means unlimited, and rely on
     * activity {@see ActivityOptions::$scheduleToCloseTimeout} to stop.
     *
     * @var positive-int
     */
    #[Marshal(name: 'MaximumAttempts')]
    public int $maximumAttempts = 0;

    /**
     * Non-Retriable errors. This is optional. Temporal server will stop retry
     * if error type matches this list.
     *
     * @var string[]
     */
    #[Marshal(name: 'NonRetryableErrorTypes')]
    public array $nonRetryableErrorTypes = [];
}
