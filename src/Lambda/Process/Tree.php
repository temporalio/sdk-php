<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\Process;

use Temporal\Lambda\Clock;

final class Tree
{
    private const REAP_TIMEOUT_MS = 200;
    private const REAP_POLL_INTERVAL_US = 10_000;

    /**
     * @return list<int>
     */
    public static function childrenOf(int $pid): array
    {
        $children = [];

        $statFiles = \glob('/proc/[0-9]*/stat');

        foreach ($statFiles === false ? [] : $statFiles as $statFile) {
            $stat = @\file_get_contents($statFile);
            if ($stat === false) {
                continue;
            }

            if (self::parentPid($stat) === $pid) {
                $children[] = (int) \basename(\dirname($statFile));
            }
        }

        return $children;
    }

    public static function parentPid(string $stat): ?int
    {
        $closingParen = \strrpos($stat, ')');
        if ($closingParen === false) {
            return null;
        }

        $fields = \explode(' ', \substr($stat, $closingParen + 2));
        $parent = $fields[1] ?? '';

        return \ctype_digit($parent) ? (int) $parent : null;
    }

    /**
     * @param list<int> $pids
     * @return int Number of processes the signal reached
     */
    public static function killAll(array $pids): int
    {
        if (!\function_exists('posix_kill')) {
            return 0;
        }

        $killed = 0;
        foreach ($pids as $pid) {
            if (\posix_kill($pid, Signal::SIGKILL)) {
                ++$killed;
            }
        }

        return $killed;
    }

    public static function reapReparented(): void
    {
        if (!\function_exists('pcntl_waitpid')) {
            return;
        }

        $deadline = Clock::nowMs() + self::REAP_TIMEOUT_MS;

        while (Clock::nowMs() < $deadline) {
            $reaped = \pcntl_waitpid(-1, $status, \WNOHANG);

            if ($reaped === -1) {
                return;
            }

            if ($reaped === 0) {
                \usleep(self::REAP_POLL_INTERVAL_US);
            }
        }
    }
}
