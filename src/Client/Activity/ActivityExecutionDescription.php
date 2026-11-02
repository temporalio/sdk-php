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
use Temporal\DataConverter\ValuesInterface;
use Temporal\Exception\Failure\TemporalFailure;
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
        public readonly ValuesInterface $heartbeatDetails,
        public readonly ?\DateTimeInterface $lastHeartbeatTime,
        public readonly ?\DateTimeInterface $lastStartedTime,
        public readonly ?TemporalFailure $lastFailure,
        public readonly string $lastWorkerIdentity,
        public readonly string $canceledReason,
        public readonly string $staticSummary,
        public readonly string $staticDetails,
    ) {}
}
