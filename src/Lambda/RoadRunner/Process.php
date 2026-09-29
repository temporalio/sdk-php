<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\RoadRunner;

use Temporal\Lambda\Clock;
use Psr\Log\LoggerInterface;
use Temporal\Lambda\Config;
use Temporal\Lambda\ProcessTree;
use Temporal\Lambda\Signal;

final class Process
{
    public const POLL_INTERVAL_US = 100_000;
    public const SIGKILL_SLACK_MS = 500;

    /** @var resource|null */
    private $process = null;

    private int $pid = 0;
    private ?int $stopDeadlineMs = null;

    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly string $requestId,
    ) {}

    public function start(): void
    {
        $command = [
            $this->config->roadRunnerBinary,
            'serve',
            '-w', $this->config->taskRoot,
            '-c', ConfigFile::PATH,
        ];

        $process = \proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => \STDERR, 2 => \STDERR],
            $pipes,
            $this->config->taskRoot,
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException('proc_open() failed for: ' . \implode(' ', $command));
        }

        $this->process = $process;
        $this->pid = \proc_get_status($process)['pid'];

        \fclose($pipes[0]);
    }

    public function exitCode(): ?int
    {
        if ($this->process === null) {
            return null;
        }

        $status = \proc_get_status($this->process);

        return $status['running'] ? null : $status['exitcode'];
    }

    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        $alreadyExited = $this->exitCode();
        if ($alreadyExited !== null) {
            $this->reap();
            $this->logger->error(
                "roadrunner had already exited with code {$alreadyExited} when the shutdown started",
                ['requestId' => $this->requestId],
            );

            return;
        }

        $startedAt = Clock::nowMs();
        $this->stopDeadlineMs ??= $startedAt + $this->config->gracefulTimeoutMs + self::SIGKILL_SLACK_MS;
        \proc_terminate($this->process, Signal::TERM);

        while (Clock::nowMs() < $this->stopDeadlineMs) {
            $exitCode = $this->exitCode();
            if ($exitCode !== null) {
                $this->reap();
                $this->logExit($exitCode, Clock::nowMs() - $startedAt);

                return;
            }

            \usleep(self::POLL_INTERVAL_US);
        }

        $this->kill($startedAt);
    }

    private function kill(int $startedAt): void
    {
        $orphans = ProcessTree::childrenOf($this->pid);

        if ($this->process !== null && !\proc_terminate($this->process, Signal::KILL)) {
            $this->logger->error('SIGKILL to roadrunner failed', ['requestId' => $this->requestId]);
        }

        $this->reap();

        $killed = ProcessTree::killAll($orphans);
        ProcessTree::reapReparented();

        $this->logger->error(\sprintf(
            'roadrunner did not stop within %dms, SIGKILLed it and %d worker process(es); '
            . 'in-flight tasks were dropped and will be retried by Temporal',
            Clock::nowMs() - $startedAt,
            $killed,
        ), ['requestId' => $this->requestId]);
    }

    private function logExit(int $exitCode, int $elapsedMs): void
    {
        if ($exitCode === 0) {
            $this->logger->info("roadrunner drained and exited in {$elapsedMs}ms", ['requestId' => $this->requestId]);

            return;
        }

        $this->logger->error(\sprintf(
            'roadrunner exited with code %d after %dms: the drain did not finish within '
            . 'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS (%dms); in-flight tasks were dropped and '
            . 'will be retried by Temporal',
            $exitCode,
            $elapsedMs,
            $this->config->gracefulTimeoutMs,
        ), ['requestId' => $this->requestId]);
    }

    private function reap(): void
    {
        if ($this->process === null) {
            return;
        }

        $process = $this->process;
        $this->process = null;
        \proc_close($process);
    }
}
