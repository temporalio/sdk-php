<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;

/**
 * Current activity options as reported for a pending activity. May differ from the options used to
 * start the activity.
 *
 * @see \Temporal\Api\Activity\V1\ActivityOptions
 * @psalm-immutable
 */
#[Immutable]
final class PendingActivityOptions
{
    /**
     * @internal
     */
    public function __construct(
        public readonly ?string $taskQueue,
        public readonly ?\DateInterval $scheduleToCloseTimeout,
        public readonly ?\DateInterval $scheduleToStartTimeout,
        public readonly ?\DateInterval $startToCloseTimeout,
        public readonly ?\DateInterval $heartbeatTimeout,
        public readonly ?PendingActivityRetryPolicy $retryPolicy,
    ) {}
}
