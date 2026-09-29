<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Psr\Log\LoggerInterface;

final class Profiler
{
    private const REPORT_INTERVAL_NS = 10_000_000_000;

    /** @var array<string, int> */
    private array $nanos = [];

    /** @var array<string, int> */
    private array $counts = [];

    private int $reportedAt;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $role,
    ) {
        $this->reportedAt = \hrtime(true);
    }

    public function add(string $label, int $startedAt): void
    {
        $now = \hrtime(true);
        $this->nanos[$label] = ($this->nanos[$label] ?? 0) + $now - $startedAt;
        $this->counts[$label] = ($this->counts[$label] ?? 0) + 1;

        if ($now - $this->reportedAt >= self::REPORT_INTERVAL_NS) {
            $this->report();
        }
    }

    public function report(): void
    {
        $this->reportedAt = \hrtime(true);
        $parts = [];
        foreach ($this->nanos as $label => $nanos) {
            $count = $this->counts[$label];
            $parts[] = \sprintf('%s: n=%d total=%.1fms avg=%.1fus', $label, $count, $nanos / 1e6, $nanos / $count / 1e3);
        }

        $usage = \getrusage();
        $cpu = $usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec'] + ($usage['ru_utime.tv_usec'] + $usage['ru_stime.tv_usec']) / 1e6;
        $this->logger->info(\sprintf(
            '[core-profile %s pid=%d] process-cpu: %.1fms | %s',
            $this->role,
            \getmypid(),
            $cpu * 1e3,
            \implode(' | ', $parts),
        ));
    }
}
