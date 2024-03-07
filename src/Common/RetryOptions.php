<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Common;

use JetBrains\PhpStorm\Pure;
use Temporal\Activity\ActivityOptions;
use Temporal\Api\Common\V1\RetryPolicy;
use Temporal\Internal\Assert;
use Temporal\Internal\Marshaller\Meta\Marshal;
use Temporal\Internal\Marshaller\Type\DurationJsonType;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;

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
 * @psalm-type ExceptionsList = array<class-string<\Throwable>>
 * @psalm-import-type DateIntervalValue from DateInterval
 * @psalm-immutable
 * @see RetryPolicy
 */
class RetryOptions extends Options
{
    /**
     * @var null
     */
    public const DEFAULT_INITIAL_INTERVAL = null;

    /**
     * @var float
     */
    public const DEFAULT_BACKOFF_COEFFICIENT = 2.0;

    /**
     * @var null
     */
    public const DEFAULT_MAXIMUM_INTERVAL = null;

    /**
     * @var int<0, max>
     */
    public const DEFAULT_MAXIMUM_ATTEMPTS = 0;

    /**
     * @psalm-var ExceptionsList
     */
    public const DEFAULT_NON_RETRYABLE_EXCEPTIONS = [];

    /**
     * Backoff interval for the first retry. If {@see RetryOptions::$backoffCoefficient}
     * is 1.0 then it is used for all retries.
     */
    #[Marshal(name: 'initial_interval', type: DurationJsonType::class, nullable: true)]
    public ?\DateInterval $initialInterval = self::DEFAULT_INITIAL_INTERVAL;

    /**
     * Coefficient used to calculate the next retry backoff interval. The next
     * retry interval is previous interval multiplied by this coefficient.
     *
     * Note: Must be greater than 1.0
     */
    #[Marshal(name: 'backoff_coefficient')]
    public float $backoffCoefficient = self::DEFAULT_BACKOFF_COEFFICIENT;

    /**
     * Maximum backoff interval between retries. Exponential backoff leads to
     * interval increase. This value is the cap of the interval.
     *
     * Default is 100x of {@see $initialInterval}.
     */
    #[Marshal(name: 'maximum_interval', type: DurationJsonType::class, nullable: true)]
    public ?\DateInterval $maximumInterval = self::DEFAULT_MAXIMUM_INTERVAL;

    /**
     * Maximum number of attempts. When exceeded the retries stop even if not
     * expired yet. If not set or set to 0, it means unlimited, and rely on
     * activity {@see ActivityOptions::$scheduleToCloseTimeout} to stop.
     *
     * @var int<0, max>
     */
    #[Marshal(name: 'maximum_attempts')]
    public int $maximumAttempts = self::DEFAULT_MAXIMUM_ATTEMPTS;

    /**
     * Non-Retriable errors. This is optional. Temporal server will stop retry
     * if error type matches this list.
     *
     * @var ExceptionsList
     */
    #[Marshal(name: 'non_retryable_error_types')]
    public array $nonRetryableExceptions = self::DEFAULT_NON_RETRYABLE_EXCEPTIONS;

    /**
     * @param MethodRetry|null $retry
     * @return self
     */
    public function mergeWith(MethodRetry $retry = null): self
    {
        $self = clone $this;

        if ($retry !== null) {
            foreach ($self->diff->getPresentPropertyNames($self) as $name) {
                $self->$name = $retry->$name;
            }
        }

        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue|null $interval
     * @return self
     */
    #[Pure]
    public function withInitialInterval($interval): self
    {
        assert(DateInterval::assert($interval) || $interval === null);

        $self = clone $this;
        $self->initialInterval = DateInterval::parseOrNull($interval, DateInterval::FORMAT_SECONDS);
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param float $coefficient
     * @return self
     */
    #[Pure]
    public function withBackoffCoefficient(float $coefficient): self
    {
        assert($coefficient >= 1.0);

        $self = clone $this;
        $self->backoffCoefficient = $coefficient;
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue|null $interval
     * @return self
     */
    #[Pure]
    public function withMaximumInterval($interval): self
    {
        assert(DateInterval::assert($interval) || $interval === null);

        $self = clone $this;
        $self->maximumInterval = DateInterval::parseOrNull($interval, DateInterval::FORMAT_SECONDS);
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param int<0, max> $attempts
     * @return self
     */
    #[Pure]
    public function withMaximumAttempts(int $attempts): self
    {
        assert($attempts >= 0);

        $self = clone $this;
        $self->maximumAttempts = $attempts;

        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param ExceptionsList $exceptions
     * @return self
     */
    #[Pure]
    public function withNonRetryableExceptions(array $exceptions): self
    {
        assert(Assert::valuesSubclassOfOrSameClass($exceptions, \Throwable::class));

        $self = clone $this;
        $self->nonRetryableExceptions = $exceptions;
        return $self;
    }

    /**
     * Converts DTO to protobuf object
     *
     * @return RetryPolicy
     * @psalm-suppress ImpureMethodCall
     */
    public function toWorkflowRetryPolicy(): RetryPolicy
    {
        $policy = new RetryPolicy();

        $policy->setInitialInterval(DateInterval::toDuration($this->initialInterval));
        $policy->setMaximumInterval(DateInterval::toDuration($this->maximumInterval));
        $policy->setBackoffCoefficient($this->backoffCoefficient);
        $policy->setMaximumAttempts($this->maximumAttempts);
        $policy->setNonRetryableErrorTypes($this->nonRetryableExceptions);

        return $policy;
    }
}
