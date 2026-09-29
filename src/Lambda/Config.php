<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda;

use Temporal\Lambda\Exception\ConfigurationException;
use Temporal\Lambda\RoadRunner\Process;

final class Config
{
    public function __construct(
        public readonly string $runtimeApi,
        public readonly string $taskRoot,
        public readonly string $roadRunnerBinary,
        public readonly string $roadRunnerConfigTemplate,
        public readonly int $shutdownBufferMs,
        public readonly int $gracefulTimeoutMs,
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->gracefulTimeoutMs < 1_000) {
            throw new ConfigurationException(
                "TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS must be at least 1000, got {$this->gracefulTimeoutMs}",
            );
        }

        $minimumBuffer = $this->minimumShutdownBufferMs();
        if ($this->shutdownBufferMs < $minimumBuffer) {
            throw new ConfigurationException(\sprintf(
                'TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS (%dms) is too small: it must reserve '
                . 'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS (%dms) plus the SIGKILL escalation, the Runtime '
                . 'API response and the poll granularity, so at least %dms',
                $this->shutdownBufferMs,
                $this->gracefulTimeoutMs,
                $minimumBuffer,
            ));
        }
    }

    private function minimumShutdownBufferMs(): int
    {
        return $this->gracefulTimeoutMs
            + Process::SIGKILL_SLACK_MS
            + RuntimeApi::RESPONSE_RESERVE_MS
            + 2 * \intdiv(Process::POLL_INTERVAL_US, 1000);
    }
}
