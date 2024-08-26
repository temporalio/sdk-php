<?php

declare(strict_types=1);

namespace Temporal\Client\Common;

use JetBrains\PhpStorm\Pure;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Support\DateInterval;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 * @psalm-immutable
 */
final class RpcRetryOptions extends RetryOptions
{
    /**
     * Interval of the first retry, on congestion related failures (i.e. RESOURCE_EXHAUSTED errors).
     *
     * If coefficient is 1.0 then it is used for all retries. Defaults to 1000ms.
     */
    public ?\DateInterval $congestionInitialInterval = null;

    /**
     * Maximum amount of jitter to apply.
     * Must be lower than 1.
     *
     * 0.1 means that actual retry time can be +/- 10% of the calculated time.
     */
    public float $maximumJitterCoefficient = 0.1;

    /**
     * Converts {@see RetryOptions} to {@see RpcRetryOptions}.
     *
     * @internal
     */
    public static function fromRetryOptions(RetryOptions $options): self
    {
        return $options instanceof self ? $options : (new self())
            ->withInitialInterval($options->initialInterval)
            ->withBackoffCoefficient($options->backoffCoefficient)
            ->withMaximumInterval($options->maximumInterval)
            ->withMaximumAttempts($options->maximumAttempts)
            ->withNonRetryableExceptions($options->nonRetryableExceptions);
    }

    /**
     * Interval of the first retry, on congestion related failures (i.e. RESOURCE_EXHAUSTED errors).
     * If coefficient is 1.0 then it is used for all retries. Defaults to 1000ms.
     *
     * @param DateIntervalValue|null $interval Interval to wait on first retry, on congestion failures.
     *        Defaults to 1000ms, which is used if set to {@see null}.
     *
     * @return self
     *
     * @psalm-suppress ImpureMethodCall
     */
    #[Pure]
    public function withCongestionInitialInterval($interval): self
    {
        $interval === null || DateInterval::assert($interval) or throw new \InvalidArgumentException(
            'Invalid interval value.'
        );

        $self = clone $this;
        $self->congestionInitialInterval = DateInterval::parseOrNull($interval, DateInterval::FORMAT_SECONDS);
        return $self;
    }

    /**
     * Maximum amount of jitter to apply.
     *
     * 0.2 means that actual retry time can be +/- 20% of the calculated time.
     * Set to 0 to disable jitter. Must be lower than 1.
     *
     * @param null|float $coefficient Maximum amount of jitter.
     *        Default will be used if set to {@see null}.
     *
     * @return self
     */
    #[Pure]
    public function withMaximumJitterCoefficient(?float $coefficient): self
    {
        $coefficient === null || ($coefficient >= 0.0 && $coefficient < 1.0) or throw new \InvalidArgumentException(
            'Maximum jitter coefficient must be in range [0, 1).'
        );

        $self = clone $this;
        $self->maximumJitterCoefficient = $coefficient ?? 0.1;
        return $self;
    }
}
