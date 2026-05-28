<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;

/**
 * Activity was paused by manual intervention.
 *
 * @see \Temporal\Api\Workflow\V1\PendingActivityInfo\PauseInfo\Manual
 * @psalm-immutable
 */
#[Immutable]
final class PendingActivityPauseInfoManual
{
    /**
     * @internal
     */
    public function __construct(
        public readonly string $identity,
        public readonly string $reason,
    ) {}
}
