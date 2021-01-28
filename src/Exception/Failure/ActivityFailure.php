<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

class ActivityFailure extends TemporalFailure
{
    private int $scheduledEventId;
    private int $startedEventId;
    private string $activityType;
    private string $activityId;
    private string $identity;
    private int $retryState;

    /**
     * @param int $scheduledEventId
     * @param int $startedEventId
     * @param string $activityType
     * @param string $activityId
     * @param int $retryState
     * @param string $identity
     * @param \Throwable|null $previous
     */
    public function __construct(
        int $scheduledEventId,
        int $startedEventId,
        string $activityType,
        string $activityId,
        int $retryState,
        string $identity,
        \Throwable $previous = null
    ) {
        parent::__construct(
            self::buildMessage(
                [
                    'scheduledEventId' => $scheduledEventId,
                    'startedEventId' => $startedEventId,
                    'activityType' => $activityType,
                    'activityId' => $activityId,
                    'identity' => $identity,
                    'retryState' => $retryState,
                ]
            ),
            null,
            $previous
        );

        $this->scheduledEventId = $scheduledEventId;
        $this->startedEventId = $startedEventId;
        $this->activityType = $activityType;
        $this->activityId = $activityId;
        $this->identity = $identity;
        $this->retryState = $retryState;
    }

    /**
     * @return int
     */
    public function getScheduledEventId(): int
    {
        return $this->scheduledEventId;
    }

    /**
     * @return int
     */
    public function getStartedEventId(): int
    {
        return $this->startedEventId;
    }

    /**
     * @return string
     */
    public function getActivityType(): string
    {
        return $this->activityType;
    }

    /**
     * @return string
     */
    public function getActivityId(): string
    {
        return $this->activityId;
    }

    /**
     * @return string
     */
    public function getIdentity(): string
    {
        return $this->identity;
    }

    /**
     * @return int
     */
    public function getRetryState(): int
    {
        return $this->retryState;
    }
}
