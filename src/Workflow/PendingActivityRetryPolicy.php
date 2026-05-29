<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;

/**
 * Retry policy as reported for a pending activity.
 *
 * @see \Temporal\Api\Common\V1\RetryPolicy
 * @psalm-immutable
 */
#[Immutable]
final class PendingActivityRetryPolicy
{
    /**
     * @param list<string> $nonRetryableErrorTypes
     *
     * @internal
     */
    public function __construct(
        public readonly ?\DateInterval $initialInterval,
        public readonly float $backoffCoefficient,
        public readonly ?\DateInterval $maximumInterval,
        public readonly int $maximumAttempts,
        public readonly array $nonRetryableErrorTypes,
    ) {}
}
