<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

/**
 * @internal
 */
final class HandlerState
{
    public int $updates = 0;
    public int $signals = 0;
}
