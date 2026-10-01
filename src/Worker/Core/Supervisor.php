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

/**
 * @internal
 */
final class Supervisor
{
    private const POLL_INTERVAL_US = 100_000;
    private const MIN_CHILD_UPTIME_SECONDS = 1;
    private const MAX_RESTART_DELAY_SECONDS = 30;
    private const MAX_RESTART_DELAY_EXPONENT = 5;
    private const SIGNAL_EXIT_CODE_BASE = 128;

    /** @var array<int, array{CoreRole, float, int, bool}> */
    private array $children = [];

    /** @var list<array{CoreRole, float, int}> */
    private array $restarts = [];

    private ?float $killAt = null;
    private int $exitCode = 0;

    public function __construct(
        private readonly ChildProcesses $processes,
        private readonly LoggerInterface $logger,
        private readonly float $stopTimeoutSeconds,
    ) {}

    /**
     * @param list<CoreRole> $roles
     */
    public function run(array $roles): int
    {
        foreach ($roles as $role) {
            $this->start($role, 0, true);
        }

        \pcntl_async_signals(true);
        \pcntl_signal(\SIGTERM, $this->stop(...), false);
        \pcntl_signal(\SIGINT, $this->stop(...), false);

        while ($this->children !== [] || ($this->killAt === null && $this->restarts !== [])) {
            $pid = \pcntl_wait($status, \WNOHANG);
            if ($pid > 0) {
                $this->onExit($pid, $status);
                continue;
            }
            $this->restartDue();
            if ($this->killAt !== null && \microtime(true) >= $this->killAt) {
                $this->signalChildren(\SIGKILL);
            }
            \usleep(self::POLL_INTERVAL_US);
        }

        return $this->exitCode;
    }

    private function stop(): void
    {
        $this->killAt ??= \microtime(true) + $this->stopTimeoutSeconds;
        $this->restarts = [];
        $this->signalChildren(\SIGTERM);
    }

    private function signalChildren(int $signal): void
    {
        foreach (\array_keys($this->children) as $pid) {
            \posix_kill($pid, $signal);
        }
    }

    private function start(CoreRole $role, int $failedStarts, bool $initial): void
    {
        $this->children[$this->processes->start($role)] = [$role, \microtime(true), $failedStarts, $initial];
    }

    private function onExit(int $pid, int $status): void
    {
        $this->processes->release($pid);
        if (!isset($this->children[$pid])) {
            return;
        }
        [$role, $startedAt, $failedStarts, $initial] = $this->children[$pid];
        unset($this->children[$pid]);
        $code = \pcntl_wifsignaled($status) ? self::SIGNAL_EXIT_CODE_BASE + \pcntl_wtermsig($status) : \pcntl_wexitstatus($status);
        if ($this->killAt !== null) {
            $this->exitCode = \max($this->exitCode, $code);
            return;
        }

        $this->logger->error(\sprintf('Worker process (%s) exited with code %d', $role->value, $code));
        $crashedAtStart = \microtime(true) - $startedAt < self::MIN_CHILD_UPTIME_SECONDS;
        if ($crashedAtStart && $initial) {
            $this->logger->error(\sprintf('Worker process (%s) exited during startup, stopping', $role->value));
            $this->exitCode = \max($this->exitCode, $code, 1);
            $this->stop();
            return;
        }

        $failedStarts = $crashedAtStart ? $failedStarts + 1 : 0;
        $delay = $failedStarts === 0 ? 0 : \min(self::MAX_RESTART_DELAY_SECONDS, 2 ** \min($failedStarts, self::MAX_RESTART_DELAY_EXPONENT));
        $this->restarts[] = [$role, \microtime(true) + $delay, $failedStarts];
    }

    private function restartDue(): void
    {
        $now = \microtime(true);
        foreach ($this->restarts as $i => [$role, $at, $failedStarts]) {
            if ($at <= $now) {
                unset($this->restarts[$i]);
                $this->start($role, $failedStarts, false);
            }
        }
        $this->restarts = \array_values($this->restarts);
    }
}
