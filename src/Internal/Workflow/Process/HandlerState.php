<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

/**
 * @internal
 */
final class HandlerState
{
    private int $updates = 0;
    private int $signals = 0;

    public function hasRunningHandlers(): bool
    {
        return $this->updates > 0 || $this->signals > 0;
    }

    public function addUpdate($name): int
    {
        ++$this->updates;
        return 0;
    }

    public function removeUpdate(int $updateId): void
    {
        --$this->updates;
    }

    public function addSignal($name): int
    {
        ++$this->signals;
        return 0;
    }

    public function removeSignal(int $signalId): void
    {
        --$this->signals;
    }
}
