<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;
use Temporal\Activity\ActivityType;
use Temporal\Common\Priority;
use Temporal\Common\Versioning\WorkerDeploymentVersion;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\TemporalFailure;

/**
 * DTO that contains information about a pending activity of a Workflow Execution.
 *
 * @see \Temporal\Api\Workflow\V1\PendingActivityInfo
 * @psalm-immutable
 */
#[Immutable]
final class PendingActivityInfo
{
    /**
     * @internal
     */
    public function __construct(
        public readonly string $activityId,
        public readonly ActivityType $activityType,
        public readonly PendingActivityState $state,
        public readonly ValuesInterface $heartbeatDetails,
        public readonly ?\DateTimeInterface $lastHeartbeatTime,
        public readonly ?\DateTimeInterface $lastStartedTime,
        public readonly int $attempt,
        public readonly int $maximumAttempts,
        public readonly ?\DateTimeInterface $scheduledTime,
        public readonly ?\DateTimeInterface $expirationTime,

        /**
         * The failure of the last activity attempt. Null if the activity has not failed yet.
         */
        public readonly ?TemporalFailure $lastFailure,
        public readonly string $lastWorkerIdentity,

        /**
         * The time activity will wait until the next retry.
         * If the activity is currently running it will be the next retry interval if the activity failed.
         * If the activity is currently waiting it will be the current retry interval.
         * If there will be no retry it will be null.
         */
        public readonly ?\DateInterval $currentRetryInterval,

        /**
         * The time when the last activity attempt was completed. Null if the activity has not been
         * completed yet.
         */
        public readonly ?\DateTimeInterface $lastAttemptCompleteTime,

        /**
         * Next time when the activity will be scheduled.
         * If the activity is currently scheduled or started it will be null.
         */
        public readonly ?\DateTimeInterface $nextAttemptScheduleTime,

        /**
         * Indicates if the activity is paused.
         */
        public readonly bool $paused,

        /**
         * The Worker Deployment Version this activity was dispatched to most recently.
         * Null if the activity has not yet been dispatched or was last dispatched to an unversioned worker.
         */
        public readonly ?WorkerDeploymentVersion $lastDeploymentVersion,

        /**
         * Priority metadata.
         */
        public readonly ?Priority $priority,

        /**
         * Information about why and when the activity was paused. Null if the activity is not paused.
         */
        public readonly ?PendingActivityPauseInfo $pauseInfo,

        /**
         * Current activity options. May be different from the ones used to start the activity.
         */
        public readonly ?PendingActivityOptions $activityOptions,
    ) {}
}
