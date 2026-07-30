<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;

/**
 * Information about why and when a pending activity was paused.
 *
 * @see \Temporal\Api\Workflow\V1\PendingActivityInfo\PauseInfo
 * @psalm-immutable
 */
#[Immutable]
final class PendingActivityPauseInfo
{
    /**
     * @internal
     */
    public function __construct(
        public readonly ?\DateTimeInterface $pauseTime,
        public readonly ?PendingActivityPauseInfoManual $manual,
        public readonly ?PendingActivityPauseInfoRule $rule,
    ) {}
}
