<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use JetBrains\PhpStorm\Pure;
use Temporal\Common\ActivityIdConflictPolicy;
use Temporal\Common\ActivityIdReusePolicy;
use Temporal\Common\Priority;
use Temporal\Common\RetryOptions;
use Temporal\Common\TypedSearchAttributes;
use Temporal\Internal\Support\DateInterval;
use Temporal\Internal\Support\Options;

/**
 * @psalm-import-type DateIntervalValue from DateInterval
 */
final class StartActivityOptions extends Options
{
    public string $id = '';
    public string $taskQueue = '';
    public \DateInterval $scheduleToCloseTimeout;
    public \DateInterval $scheduleToStartTimeout;
    public \DateInterval $startToCloseTimeout;
    public \DateInterval $heartbeatTimeout;
    public \DateInterval $startDelay;
    public ?RetryOptions $retryOptions = null;
    public ActivityIdReusePolicy $idReusePolicy = ActivityIdReusePolicy::Unspecified;
    public ActivityIdConflictPolicy $idConflictPolicy = ActivityIdConflictPolicy::Unspecified;
    public ?TypedSearchAttributes $typedSearchAttributes = null;
    public string $staticSummary = '';
    public string $staticDetails = '';
    public Priority $priority;

    public function __construct()
    {
        $this->scheduleToCloseTimeout = new \DateInterval('PT0S');
        $this->scheduleToStartTimeout = new \DateInterval('PT0S');
        $this->startToCloseTimeout = new \DateInterval('PT0S');
        $this->heartbeatTimeout = new \DateInterval('PT0S');
        $this->startDelay = new \DateInterval('PT0S');
        $this->priority = Priority::new();

        parent::__construct();
    }

    /**
     * @param non-empty-string $id
     */
    #[Pure]
    public function withId(string $id): self
    {
        $self = clone $this;
        $self->id = $id;
        return $self;
    }

    /**
     * @param non-empty-string $taskQueue
     */
    #[Pure]
    public function withTaskQueue(string $taskQueue): self
    {
        $self = clone $this;
        $self->taskQueue = $taskQueue;
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     */
    #[Pure]
    public function withScheduleToCloseTimeout($timeout): self
    {
        $self = clone $this;
        $self->scheduleToCloseTimeout = self::parseInterval($timeout);
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     */
    #[Pure]
    public function withScheduleToStartTimeout($timeout): self
    {
        $self = clone $this;
        $self->scheduleToStartTimeout = self::parseInterval($timeout);
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     */
    #[Pure]
    public function withStartToCloseTimeout($timeout): self
    {
        $self = clone $this;
        $self->startToCloseTimeout = self::parseInterval($timeout);
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $timeout
     */
    #[Pure]
    public function withHeartbeatTimeout($timeout): self
    {
        $self = clone $this;
        $self->heartbeatTimeout = self::parseInterval($timeout);
        return $self;
    }

    /**
     * @psalm-suppress ImpureMethodCall
     *
     * @param DateIntervalValue $delay
     */
    #[Pure]
    public function withStartDelay($delay): self
    {
        $self = clone $this;
        $self->startDelay = self::parseInterval($delay);
        return $self;
    }

    #[Pure]
    public function withRetryOptions(?RetryOptions $options): self
    {
        $self = clone $this;
        $self->retryOptions = $options;
        return $self;
    }

    #[Pure]
    public function withIdReusePolicy(ActivityIdReusePolicy $policy): self
    {
        $self = clone $this;
        $self->idReusePolicy = $policy;
        return $self;
    }

    #[Pure]
    public function withIdConflictPolicy(ActivityIdConflictPolicy $policy): self
    {
        $self = clone $this;
        $self->idConflictPolicy = $policy;
        return $self;
    }

    #[Pure]
    public function withTypedSearchAttributes(TypedSearchAttributes $attributes): self
    {
        $self = clone $this;
        $self->typedSearchAttributes = $attributes;
        return $self;
    }

    #[Pure]
    public function withStaticSummary(string $summary): self
    {
        $self = clone $this;
        $self->staticSummary = $summary;
        return $self;
    }

    #[Pure]
    public function withStaticDetails(string $details): self
    {
        $self = clone $this;
        $self->staticDetails = $details;
        return $self;
    }

    #[Pure]
    public function withPriority(Priority $priority): self
    {
        $self = clone $this;
        $self->priority = $priority;
        return $self;
    }

    /**
     * @param DateIntervalValue $interval
     */
    private static function parseInterval($interval): \DateInterval
    {
        \assert(DateInterval::assert($interval));
        $interval = DateInterval::parse($interval, DateInterval::FORMAT_SECONDS);
        \assert($interval->totalMicroseconds >= 0);

        return $interval;
    }
}
