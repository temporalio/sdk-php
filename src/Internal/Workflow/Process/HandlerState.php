<?php

declare(strict_types=1);

namespace Temporal\Internal\Workflow\Process;

/**
 * @internal
 */
final class HandlerState
{
    /** @var array<int, array{id: non-empty-string, name: non-empty-string}> */
    private array $updates = [];

    /** @var array<int, non-empty-string> */
    private array $signals = [];

    public function hasRunningHandlers(): bool
    {
        return \count($this->updates) > 0 || \count($this->signals) > 0;
    }

    /**
     * @param non-empty-string $id
     * @param non-empty-string $name
     */
    public function addUpdate(string $id, string $name): int
    {
        $this->updates[] = ['id' => $id, 'name' => $name];
        return \array_key_last($this->updates);
    }

    public function removeUpdate(int $recordId): void
    {
        unset($this->updates[$recordId]);
    }

    /**
     * @param non-empty-string $name
     */
    public function addSignal(string $name): int
    {
        $this->signals[] = $name;
        return \array_key_last($this->signals);
    }

    public function removeSignal(int $recordId): void
    {
        unset($this->signals[$recordId]);
    }

    /**
     * @return array<non-empty-string, int<1, max>> Signal name => count
     */
    public function getRunningSignals(): array
    {
        return \array_count_values($this->signals);
    }

    /**
     * @return array<int, array{id: non-empty-string, name: non-empty-string}>
     */
    public function getRunningUpdates(): array
    {
        return $this->updates;
    }
}
