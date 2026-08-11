<?php

declare(strict_types=1);

namespace Temporal\Activity;

/**
 * Provides the reasons for the activity's cancellation.
 */
final class ActivityCancellationDetails
{
    /**
     * @internal
     */
    public function __construct(
        public readonly bool $notFound = false,
        public readonly bool $cancelRequested = false,
        public readonly bool $paused = false,
        public readonly bool $timedOut = false,
        public readonly bool $workerShutdown = false,
        public readonly bool $reset = false,
    ) {}
}
