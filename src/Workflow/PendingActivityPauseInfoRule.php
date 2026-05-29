<?php

declare(strict_types=1);

namespace Temporal\Workflow;

use JetBrains\PhpStorm\Immutable;

/**
 * Activity was paused by a rule.
 *
 * @see \Temporal\Api\Workflow\V1\PendingActivityInfo\PauseInfo\Rule
 * @psalm-immutable
 */
#[Immutable]
final class PendingActivityPauseInfoRule
{
    /**
     * @internal
     */
    public function __construct(
        public readonly string $ruleId,
        public readonly string $identity,
        public readonly string $reason,
    ) {}
}
