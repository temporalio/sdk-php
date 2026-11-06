<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Activity;

use JetBrains\PhpStorm\Immutable;
use Temporal\Client\Workflow\UserMetadata;
use Temporal\Common\Priority;
use Temporal\Common\Versioning\WorkerDeploymentVersion;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\TemporalFailure;
use Temporal\Workflow\PendingActivityRetryPolicy;
use Temporal\Workflow\PendingActivityState;

/**
 * @see \Temporal\Api\Activity\V1\ActivityExecutionInfo
 * @psalm-immutable
 */
#[Immutable]
final class ActivityExecutionDescription
{
    /**
     * @internal
     */
    public function __construct(
        public readonly ActivityExecutionInfo $info,
        public readonly PendingActivityState $runState,
        public readonly int $attempt,
        public readonly ?\DateInterval $scheduleToCloseTimeout,
        public readonly ?\DateInterval $scheduleToStartTimeout,
        public readonly ?\DateInterval $startToCloseTimeout,
        public readonly ?\DateInterval $heartbeatTimeout,
        public readonly ?\DateInterval $startDelay,
        public readonly ?PendingActivityRetryPolicy $retryPolicy,
        public readonly ?Priority $priority,
        public readonly ValuesInterface $heartbeatDetails,
        public readonly int $totalHeartbeatCount,
        public readonly ?\DateTimeInterface $lastHeartbeatTime,
        public readonly ?\DateTimeInterface $lastStartedTime,
        public readonly ?\DateTimeInterface $lastAttemptCompleteTime,
        public readonly ?\DateTimeInterface $nextAttemptScheduleTime,
        public readonly ?\DateTimeInterface $expirationTime,
        public readonly ?\DateInterval $currentRetryInterval,
        public readonly ?TemporalFailure $lastFailure,
        public readonly string $lastWorkerIdentity,
        public readonly ?WorkerDeploymentVersion $lastDeploymentVersion,
        public readonly ?string $canceledReason,
        public readonly UserMetadata $userMetadata,
    ) {}
}
