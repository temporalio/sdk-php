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

    public static function fromEnvironment(Environment $environment): self
    {
        if ($environment->runtimeApi === null) {
            throw new ConfigurationException(
                'AWS_LAMBDA_RUNTIME_API is not set: this script must run as a Lambda runtime',
            );
        }

        $taskRoot = $environment->taskRoot ?? '/var/task';

        return new self(
            $environment->runtimeApi,
            $taskRoot,
            $environment->roadRunnerBinary ?? 'rr',
            $environment->roadRunnerConfig ?? $taskRoot . '/.rr.yaml',
            self::milliseconds(
                'TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS',
                $environment->shutdownBufferMs,
                7_000,
            ),
            self::milliseconds(
                'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS',
                $environment->gracefulTimeoutMs,
                5_000,
            ),
        );
    }

    private static function milliseconds(string $name, ?string $value, int $default): int
    {
        if ($value === null) {
            return $default;
        }

        if (!\ctype_digit($value)) {
            throw new ConfigurationException("{$name} must be a positive integer, got \"{$value}\"");
        }

        return (int) $value;
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

        $this->validateTemplate();
    }

    private function minimumShutdownBufferMs(): int
    {
        return $this->gracefulTimeoutMs
            + Process::SIGKILL_SLACK_MS
            + RuntimeApi::RESPONSE_RESERVE_MS
            + 2 * \intdiv(Process::POLL_INTERVAL_US, 1000);
    }

    private function validateTemplate(): void
    {
        if (!\is_file($this->roadRunnerConfigTemplate)) {
            throw new ConfigurationException("RoadRunner config not found: {$this->roadRunnerConfigTemplate}");
        }

        $template = \file_get_contents($this->roadRunnerConfigTemplate);
        if ($template === false) {
            throw new ConfigurationException("RoadRunner config is not readable: {$this->roadRunnerConfigTemplate}");
        }

        if (\preg_match('/^rpc:/m', $template) !== 1) {
            throw new ConfigurationException(
                "{$this->roadRunnerConfigTemplate} must define an rpc section: Activity::heartbeat() "
                . 'reaches RoadRunner over goridge RPC, and without it every heartbeat fails with '
                . 'connection refused',
            );
        }

        if (\preg_match('/^endure:/m', $template) === 1) {
            throw new ConfigurationException(
                "{$this->roadRunnerConfigTemplate} must not define an endure section: the runtime owns "
                . 'endure.grace_period, derived from TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS',
            );
        }
    }
}
