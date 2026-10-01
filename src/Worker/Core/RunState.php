<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

final class RunState
{
    public const TIMER = 1;
    public const ACTIVITY = 2;
    public const LOCAL_ACTIVITY = 3;
    public const CHILD = 4;
    public const SIGNAL_EXTERNAL = 5;
    public const CANCEL_EXTERNAL = 6;

    public int $versioningBehavior = 0;
    public readonly PatchVersions $patches;
    public readonly LocalActivityRetries $localActivities;

    /** @var array<int, true> */
    private array $tryCancelActivities = [];

    /** @var array<string, string> */
    private array $updates = [];

    /** @var array<int, string> */
    private array $childWorkflowIds = [];

    /** @var array<int, array{string, string}|\Throwable> */
    private array $childExecutions = [];

    /** @var array<int, list<int>> */
    private array $childWaiters = [];

    /** @var array<int, int> */
    private array $requests = [];

    /** @var array<int, array{int, int}> */
    private array $commands = [];

    private int $seq = 0;

    public function __construct(
        public readonly string $runId,
    ) {
        $this->patches = new PatchVersions();
        $this->localActivities = new LocalActivityRetries();
    }

    public function bind(int $requestId, int $type): int
    {
        $seq = ++$this->seq;
        $this->requests[$seq] = $requestId;
        $this->commands[$requestId] = [$type, $seq];

        return $seq;
    }

    public function rebind(int $seq, int $type): int
    {
        return $this->bind($this->release($seq), $type);
    }

    public function requestId(int $seq): int
    {
        return $this->requests[$seq] ?? throw new \OutOfBoundsException("Unknown command sequence $seq in run {$this->runId}");
    }

    public function release(int $seq): int
    {
        $requestId = $this->requestId($seq);
        unset($this->requests[$seq], $this->commands[$requestId]);

        return $requestId;
    }

    /**
     * @return array{int, int}|null
     */
    public function command(int $requestId): ?array
    {
        return $this->commands[$requestId] ?? null;
    }

    public function markTryCancel(int $seq): void
    {
        $this->tryCancelActivities[$seq] = true;
    }

    public function takeTryCancel(int $seq): bool
    {
        $tryCancel = isset($this->tryCancelActivities[$seq]);
        unset($this->tryCancelActivities[$seq]);

        return $tryCancel;
    }

    public function startUpdate(string $updateId, string $protocolInstanceId): void
    {
        $this->updates[$updateId] = $protocolInstanceId;
    }

    public function updateProtocolInstanceId(string $updateId): string
    {
        return $this->updates[$updateId] ?? $updateId;
    }

    public function finishUpdate(string $updateId): void
    {
        unset($this->updates[$updateId]);
    }

    public function startChild(int $requestId, string $workflowId): void
    {
        $this->childWorkflowIds[$requestId] = $workflowId;
    }

    /**
     * @return array{string, string}
     */
    public function childStarted(int $requestId, string $runId): array
    {
        return $this->childExecutions[$requestId] = [$this->childWorkflowIds[$requestId], $runId];
    }

    public function childStartFailed(int $requestId, \Throwable $error): void
    {
        $this->childExecutions[$requestId] = $error;
    }

    /**
     * @return array{string, string}|\Throwable|null
     */
    public function childExecution(int $requestId): array|\Throwable|null
    {
        return $this->childExecutions[$requestId] ?? null;
    }

    public function awaitChildExecution(int $requestId, int $waiterId): void
    {
        $this->childWaiters[$requestId][] = $waiterId;
    }

    /**
     * @return list<int>
     */
    public function takeChildWaiters(int $requestId): array
    {
        $waiters = $this->childWaiters[$requestId] ?? [];
        unset($this->childWaiters[$requestId]);

        return $waiters;
    }
}
