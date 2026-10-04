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
    public const STOP_SIGNALS = [\SIGTERM, \SIGINT];
    private const POLL_INTERVAL_US = 100_000;
    private const STARTUP_CRASH_SECONDS = 1;
    private const HEALTHY_UPTIME_SECONDS = 60;
    private const MAX_RESTART_DELAY_SECONDS = 30;
    private const SIGNAL_EXIT_CODE_BASE = 128;

    /** @var array<int, array{CoreRole, float, int, bool}> */
    private array $children = [];

    /** @var list<array{CoreRole, float, int}> */
    private array $restarts = [];

    private ?float $killAt = null;
    private int $exitCode = 0;
    private ?\Throwable $startFailure = null;

    /**
     * @param \Closure(CoreRole): int $start
     * @param \Closure(int): void $release
     */
    public function __construct(
        private readonly \Closure $start,
        private readonly \Closure $release,
        private readonly LoggerInterface $logger,
        private readonly float $stopTimeoutSeconds,
    ) {}

    /**
     * @param list<CoreRole> $roles
     */
    public function run(array $roles): int
    {
        \pcntl_async_signals(true);
        foreach (self::STOP_SIGNALS as $signal) {
            \pcntl_signal($signal, $this->stop(...), false);
        }

        try {
            foreach ($roles as $role) {
                if (!$this->tryStart($role, 0, true)) {
                    break;
                }
            }
            $this->wait();
        } finally {
            foreach (self::STOP_SIGNALS as $signal) {
                \pcntl_signal($signal, \SIG_DFL);
            }
        }

        if ($this->startFailure !== null) {
            throw $this->startFailure;
        }

        return $this->exitCode;
    }

    private function wait(): void
    {
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

    private function tryStart(CoreRole $role, int $failedStarts, bool $initial): bool
    {
        try {
            $pid = ($this->start)($role);
        } catch (\Throwable $e) {
            $this->logger->error(\sprintf('Unable to start a worker process (%s): %s', $role->value, $e->getMessage()));
            $this->startFailure = $e;
            $this->stop();
            return false;
        }

        $this->children[$pid] = [$role, \microtime(true), $failedStarts, $initial];
        if ($this->killAt !== null) {
            \posix_kill($pid, \SIGTERM);
        }

        return true;
    }

    private function onExit(int $pid, int $status): void
    {
        ($this->release)($pid);
        if (!isset($this->children[$pid])) {
            return;
        }
        [$role, $startedAt, $failedStarts, $initial] = $this->children[$pid];
        unset($this->children[$pid]);
        $code = \pcntl_wifsignaled($status) ? self::SIGNAL_EXIT_CODE_BASE + \pcntl_wtermsig($status) : \pcntl_wexitstatus($status);
        $this->exitCode = \max($this->exitCode, $code);
        if ($this->killAt !== null) {
            return;
        }

        $this->logger->error(\sprintf('Worker process (%s) exited with code %d', $role->value, $code));
        $uptime = \microtime(true) - $startedAt;
        if ($initial && $uptime < self::STARTUP_CRASH_SECONDS) {
            $this->logger->error(\sprintf('Worker process (%s) exited during startup, stopping', $role->value));
            $this->exitCode = \max($this->exitCode, 1);
            $this->stop();
            return;
        }

        $failedStarts = $uptime < self::HEALTHY_UPTIME_SECONDS ? $failedStarts + 1 : 0;
        $delay = $failedStarts === 0 ? 0 : \min(self::MAX_RESTART_DELAY_SECONDS, 2 ** ($failedStarts - 1));
        $this->restarts[] = [$role, \microtime(true) + (float) $delay, $failedStarts];
    }

    private function restartDue(): void
    {
        $now = \microtime(true);
        foreach ($this->restarts as $i => [$role, $at, $failedStarts]) {
            if ($this->killAt !== null) {
                return;
            }
            if ($at > $now) {
                continue;
            }
            unset($this->restarts[$i]);
            $this->tryStart($role, $failedStarts, false);
        }
        $this->restarts = \array_values($this->restarts);
    }
}
