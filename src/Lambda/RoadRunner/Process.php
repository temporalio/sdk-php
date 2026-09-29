<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda\RoadRunner;

use Psr\Log\LoggerInterface;
use Temporal\Lambda\Clock;
use Temporal\Lambda\Config;
use Temporal\Lambda\Process\Handle;
use Temporal\Lambda\Process\Signal;
use Temporal\Lambda\Process\Tree;

final class Process
{
    public const POLL_INTERVAL_US = 100_000;
    public const SIGKILL_SLACK_MS = 500;

    private ?Handle $handle = null;
    private ?int $stopDeadlineMs = null;

    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly string $requestId,
    ) {}

    public function start(): void
    {
        $this->handle = Handle::start([
            $this->config->roadRunnerBinary,
            'serve',
            '-w', $this->config->taskRoot,
            '-c', ConfigFile::PATH,
        ], $this->config->taskRoot);
    }

    public function exitCode(): ?int
    {
        return $this->handle?->exitCode();
    }

    public function stop(): void
    {
        if ($this->handle === null) {
            return;
        }

        $alreadyExited = $this->handle->exitCode();
        if ($alreadyExited !== null) {
            $this->release();
            $this->logger->error(
                "roadrunner had already exited with code {$alreadyExited} when the shutdown started",
                ['requestId' => $this->requestId],
            );

            return;
        }

        $startedAt = Clock::nowMs();
        $this->stopDeadlineMs ??= $startedAt + $this->config->gracefulTimeoutMs + self::SIGKILL_SLACK_MS;
        $this->handle->signal(Signal::SIGTERM);

        while (Clock::nowMs() < $this->stopDeadlineMs) {
            $exitCode = $this->handle->exitCode();
            if ($exitCode !== null) {
                $this->release();
                $this->logExit($exitCode, Clock::nowMs() - $startedAt);

                return;
            }

            \usleep(self::POLL_INTERVAL_US);
        }

        $this->kill($startedAt);
    }

    private function kill(int $startedAt): void
    {
        $handle = $this->handle;
        if ($handle === null) {
            return;
        }

        $orphans = Tree::childrenOf($handle->pid());

        if (!$handle->signal(Signal::SIGKILL)) {
            $this->logger->error('SIGKILL to roadrunner failed', ['requestId' => $this->requestId]);
        }

        $this->release();

        $killed = Tree::killAll($orphans);
        Tree::reapReparented();

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

    private function release(): void
    {
        $this->handle?->reap();
        $this->handle = null;
    }
}
