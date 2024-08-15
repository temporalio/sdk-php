<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

/**
 * @internal
 */
final class HandlerState
{
    private array $updates = [];
    private array $signals = [];

    public function hasRunningHandlers(): bool
    {
        return \count($this->updates) > 0 || \count($this->signals) > 0;
    }

    public function addUpdate($name): int
    {
        $this->updates[] = $name;
        return \array_key_last($this->updates);
    }

    public function removeUpdate(int $updateId): void
    {
        unset($this->updates[$updateId]);
    }

    public function addSignal($name): int
    {
        $this->signals[] = $name;
        return \array_key_last($this->signals);
    }

    public function removeSignal(int $signalId): void
    {
        unset($this->signals[$signalId]);
    }

    /**
     * @return list<non-empty-string> List of signal names
     */
    public function getRunningSignals(): array
    {
        return \array_unique($this->signals);
    }

    /**
     * @return list<non-empty-string> List of update names
     */
    public function getRunningUpdates(): array
    {
        return \array_unique($this->updates);
    }
}
