<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Temporal\Lambda\Config;
use Temporal\Lambda\Exception\ConfigurationException;
use Temporal\Lambda\RoadRunner\Process;
use Temporal\Lambda\RuntimeApi;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(Config::class)]
#[UsesClass(ConfigurationException::class)]
final class ConfigTestCase extends AbstractUnit
{
    private const MINIMUM_GRACEFUL_MS = 1_000;

    public function testBufferOneMillisecondBelowTheMinimumIsRejected(): void
    {
        $minimum = self::minimumBuffer(self::MINIMUM_GRACEFUL_MS);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(\sprintf(
            'TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS (%dms) is too small: it must reserve '
            . 'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS (%dms) plus the SIGKILL escalation, the Runtime '
            . 'API response and the poll granularity, so at least %dms',
            $minimum - 1,
            self::MINIMUM_GRACEFUL_MS,
            $minimum,
        ));

        self::config(shutdownBufferMs: $minimum - 1, gracefulTimeoutMs: self::MINIMUM_GRACEFUL_MS);
    }

    public function testBufferExactlyAtTheMinimumIsAccepted(): void
    {
        $minimum = self::minimumBuffer(self::MINIMUM_GRACEFUL_MS);

        $config = self::config(shutdownBufferMs: $minimum, gracefulTimeoutMs: self::MINIMUM_GRACEFUL_MS);

        self::assertSame($minimum, $config->shutdownBufferMs);
    }

    public function testABiggerGracefulTimeoutRaisesTheMinimumBuffer(): void
    {
        $accepted = self::minimumBuffer(5_000);

        $this->expectException(ConfigurationException::class);

        self::config(shutdownBufferMs: $accepted - 1, gracefulTimeoutMs: 5_000);
    }

    public function testGracefulTimeoutBelowOneSecondIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS must be at least 1000, got 999',
        );

        self::config(shutdownBufferMs: 30_000, gracefulTimeoutMs: 999);
    }

    private static function minimumBuffer(int $gracefulTimeoutMs): int
    {
        return $gracefulTimeoutMs
            + Process::SIGKILL_SLACK_MS
            + RuntimeApi::RESPONSE_RESERVE_MS
            + 2 * \intdiv(Process::POLL_INTERVAL_US, 1000);
    }

    private static function config(int $shutdownBufferMs, int $gracefulTimeoutMs): Config
    {
        return new Config(
            runtimeApi: '127.0.0.1:9001',
            taskRoot: '/var/task',
            roadRunnerBinary: 'rr',
            roadRunnerConfigTemplate: '/var/task/.rr.yaml',
            shutdownBufferMs: $shutdownBufferMs,
            gracefulTimeoutMs: $gracefulTimeoutMs,
        );
    }
}
