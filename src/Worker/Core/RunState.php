<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Coresdk\Workflow_commands\ScheduleLocalActivity;

final class RunState
{
    public const TIMER = 1;
    public const ACTIVITY = 2;
    public const LOCAL_ACTIVITY = 3;
    public const CHILD = 4;
    public const SIGNAL_EXTERNAL = 5;
    public const CANCEL_EXTERNAL = 6;

    public int $versioningBehavior = 0;

    /** @var array<string, int> */
    public array $patches = [];

    /** @var array<string, int> */
    public array $versions = [];

    /** @var array<int, ScheduleLocalActivity> */
    public array $localActivities = [];

    /** @var array<int, ScheduleLocalActivity> */
    public array $localActivityBackoffs = [];

    /** @var array<int, true> */
    public array $tryCancelActivities = [];

    /** @var array<string, string> */
    public array $updates = [];

    /** @var array<int, string> */
    public array $childWorkflowIds = [];

    /** @var array<int, array{string, string}|\Throwable> */
    public array $childExecutions = [];

    /** @var array<int, list<int>> */
    public array $childWaiters = [];

    /** @var array<int, int> */
    private array $requests = [];

    /** @var array<int, array{int, int}> */
    private array $commands = [];

    private int $seq = 0;

    public function __construct(
        public readonly string $runId,
    ) {}

    public function bind(int $requestId, int $type): int
    {
        $seq = ++$this->seq;
        $this->requests[$seq] = $requestId;
        $this->commands[$requestId] = [$type, $seq];

        return $seq;
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
}
