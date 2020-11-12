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
use Temporal\Client\Internal\Support\DataTransferObject;
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
 *
 * @psalm-type RetryOptionsArray = {
 *      initialInterval: DateIntervalFormat|null,
 *      backoffCoefficient: float,
 *      maximumInterval: DateIntervalFormat|null,
 *      maximumAttempts: positive-int,
 *      nonRetryableErrorTypes: iterable<string>,
 * }
 */
final class RetryOptions extends DataTransferObject
{
    /**
     * Backoff interval for the first retry. If {@see RetryOptions::$backoffCoefficient}
     * is 1.0 then it is used for all retries.
     *
     * @var \DateInterval|CarbonInterval|null
     */
    protected ?\DateInterval $initialInterval = null;

    /**
     * Coefficient used to calculate the next retry backoff interval. The next
     * retry interval is previous interval multiplied by this coefficient.
     *
     * Note: Must be greater than 1.0
     */
    protected float $backoffCoefficient = 2.0;

    /**
     * Maximum backoff interval between retries. Exponential backoff leads to
     * interval increase. This value is the cap of the interval.
     *
     * Default is 100x of initial interval.
     *
     * @var \DateInterval|CarbonInterval|null
     */
    protected ?\DateInterval $maximumInterval = null;

    /**
     * Maximum number of attempts. When exceeded the retries stop even if not
     * expired yet. If not set or set to 0, it means unlimited, and rely on
     * activity {@see ActivityOptions::$scheduleToCloseTimeout} to stop.
     *
     * @var positive-int
     */
    protected int $maximumAttempts = 0;

    /**
     * Non-Retriable errors. This is optional. Temporal server will stop retry
     * if error type matches this list.
     *
     * @var string[]
     */
    protected array $nonRetryableErrorTypes = [];

    /**
     * @param DateIntervalFormat|null $interval
     * @return $this
     * @throws \Exception
     */
    public function setInitialInterval($interval): self
    {
        assert($interval === null || DateInterval::assert($interval), 'Precondition failed');

        $this->initialInterval = $interval !== null ? DateInterval::parse($interval) : null;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getInitialInterval(): ?int
    {
        if ($this->initialInterval === null) {
            return null;
        }

        return CarbonInterval::make($this->initialInterval)->milliseconds;
    }

    /**
     * @param int|float $value
     * @return $this
     */
    public function setBackoffCoefficient(float $value): self
    {
        assert($value >= 1.0, 'Precondition failed');

        $this->backoffCoefficient = $value;

        return $this;
    }

    /**
     * @return float
     */
    public function getBackoffCoefficient(): float
    {
        return $this->backoffCoefficient;
    }

    /**
     * @param DateIntervalFormat|null $interval
     * @return $this
     * @throws \Exception
     */
    public function setMaximumInterval($interval): self
    {
        assert($interval === null || DateInterval::assert($interval), 'Precondition failed');

        $this->maximumInterval = $interval !== null ? DateInterval::parse($interval) : null;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaximumInterval(): ?int
    {
        if ($this->maximumInterval === null) {
            return null;
        }

        return CarbonInterval::make($this->maximumInterval)->milliseconds;
    }

    /**
     * @param int $attempts
     * @return $this
     */
    public function setMaximumAttempts(int $attempts): self
    {
        assert($attempts >= 0, 'Precondition failed');

        $this->maximumAttempts = $attempts;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaximumAttempts(): int
    {
        return $this->maximumAttempts;
    }

    /**
     * @param string[] $exceptions
     * @return $this
     */
    public function setNonRetryableErrorTypes(iterable $exceptions = []): self
    {
        $this->nonRetryableErrorTypes = Iter::toArray($exceptions);

        return $this;
    }

    /**
     * @return string[]
     */
    public function getNonRetryableErrorTypes(): array
    {
        return $this->nonRetryableErrorTypes;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->arrayKeysToUpper(parent::toArray());
    }
}
