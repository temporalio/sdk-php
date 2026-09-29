<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Temporal\Lambda\Config;
use Temporal\Lambda\Environment;
use Temporal\Lambda\Exception\ConfigurationException;
use Temporal\Lambda\RoadRunner\Process;
use Temporal\Lambda\RuntimeApi;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(Config::class)]
#[UsesClass(ConfigurationException::class)]
#[UsesClass(Environment::class)]
final class ConfigTestCase extends AbstractUnit
{
    private const MINIMUM_GRACEFUL_MS = 1_000;

    private string $template;
    private ?string $taskRoot = null;

    public function testBufferOneMillisecondBelowTheMinimumIsRejected(): void
    {
        $minimum = self::MINIMUM_GRACEFUL_MS
            + Process::SIGKILL_SLACK_MS
            + RuntimeApi::RESPONSE_RESERVE_MS
            + 2 * \intdiv(Process::POLL_INTERVAL_US, 1000);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(\sprintf(
            'TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS (%dms) is too small: it must reserve '
            . 'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS (%dms) plus the SIGKILL escalation, the Runtime '
            . 'API response and the poll granularity, so at least %dms',
            $minimum - 1,
            self::MINIMUM_GRACEFUL_MS,
            $minimum,
        ));

        $this->config(shutdownBufferMs: $minimum - 1, gracefulTimeoutMs: self::MINIMUM_GRACEFUL_MS);
    }

    public function testBufferExactlyAtTheMinimumIsAccepted(): void
    {
        $minimum = self::MINIMUM_GRACEFUL_MS
            + Process::SIGKILL_SLACK_MS
            + RuntimeApi::RESPONSE_RESERVE_MS
            + 2 * \intdiv(Process::POLL_INTERVAL_US, 1000);

        $config = $this->config(shutdownBufferMs: $minimum, gracefulTimeoutMs: self::MINIMUM_GRACEFUL_MS);

        self::assertSame($minimum, $config->shutdownBufferMs);
    }

    public function testGracefulTimeoutBelowOneSecondIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS must be at least 1000, got 999',
        );

        $this->config(shutdownBufferMs: 30_000, gracefulTimeoutMs: 999);
    }

    public function testMissingConfigTemplateIsRejected(): void
    {
        $missing = $this->template . '.missing';

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("RoadRunner config not found: {$missing}");

        $this->config(template: $missing);
    }

    public function testTemplateWithoutRpcSectionIsRejected(): void
    {
        \file_put_contents($this->template, "version: \"3\"\nserver:\n  command: \"php worker.php\"\n");

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "{$this->template} must define an rpc section: Activity::heartbeat() reaches RoadRunner "
            . 'over goridge RPC, and without it every heartbeat fails with connection refused',
        );

        $this->config();
    }

    public function testTemplateWithEndureSectionIsRejected(): void
    {
        \file_put_contents(
            $this->template,
            "rpc:\n  listen: tcp://127.0.0.1:6001\nendure:\n  grace_period: 30s\n",
        );

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "{$this->template} must not define an endure section: the runtime owns endure.grace_period, "
            . 'derived from TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS',
        );

        $this->config();
    }

    public function testIndentedRpcKeyDoesNotSatisfyTheRpcRequirement(): void
    {
        \file_put_contents($this->template, "temporal:\n  rpc: nested\n");

        $this->expectException(ConfigurationException::class);

        $this->config();
    }

    public function testEnvironmentWithoutRuntimeApiIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'AWS_LAMBDA_RUNTIME_API is not set: this script must run as a Lambda runtime',
        );

        Config::fromEnvironment(new Environment(roadRunnerConfig: $this->template));
    }

    public function testNonNumericTimeoutIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS must be a positive integer, got "5s"',
        );

        Config::fromEnvironment(new Environment(
            runtimeApi: '127.0.0.1:9001',
            roadRunnerConfig: $this->template,
            gracefulTimeoutMs: '5s',
        ));
    }

    public function testEnvironmentOverridesReachTheConfig(): void
    {
        $config = Config::fromEnvironment(new Environment(
            runtimeApi: '127.0.0.1:9001',
            roadRunnerBinary: '/opt/rr',
            roadRunnerConfig: $this->template,
            shutdownBufferMs: '9000',
            gracefulTimeoutMs: '6000',
        ));

        self::assertSame('127.0.0.1:9001', $config->runtimeApi);
        self::assertSame('/opt/rr', $config->roadRunnerBinary);
        self::assertSame($this->template, $config->roadRunnerConfigTemplate);
        self::assertSame(9_000, $config->shutdownBufferMs);
        self::assertSame(6_000, $config->gracefulTimeoutMs);
    }

    public function testDefaultsApplyWhenOnlyTheRuntimeApiIsSet(): void
    {
        $taskRoot = \sys_get_temp_dir() . '/temporal-lambda-task-' . \bin2hex(\random_bytes(6));
        \mkdir($taskRoot);
        \file_put_contents($taskRoot . '/.rr.yaml', "rpc:\n  listen: tcp://127.0.0.1:6001\n");
        $this->taskRoot = $taskRoot;

        $config = Config::fromEnvironment(new Environment(
            runtimeApi: '127.0.0.1:9001',
            taskRoot: $taskRoot,
        ));

        self::assertSame('rr', $config->roadRunnerBinary);
        self::assertSame($taskRoot . '/.rr.yaml', $config->roadRunnerConfigTemplate);
        self::assertSame(7_000, $config->shutdownBufferMs);
        self::assertSame(5_000, $config->gracefulTimeoutMs);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->template = \tempnam(\sys_get_temp_dir(), 'rr-config-');
        \file_put_contents($this->template, "rpc:\n  listen: tcp://127.0.0.1:6001\n");
    }

    protected function tearDown(): void
    {
        @\unlink($this->template);

        if ($this->taskRoot !== null) {
            @\unlink($this->taskRoot . '/.rr.yaml');
            @\rmdir($this->taskRoot);
        }

        parent::tearDown();
    }

    private function config(
        ?string $template = null,
        int $shutdownBufferMs = 30_000,
        int $gracefulTimeoutMs = 5_000,
    ): Config {
        return new Config(
            runtimeApi: '127.0.0.1:9001',
            taskRoot: \sys_get_temp_dir(),
            roadRunnerBinary: 'rr',
            roadRunnerConfigTemplate: $template ?? $this->template,
            shutdownBufferMs: $shutdownBufferMs,
            gracefulTimeoutMs: $gracefulTimeoutMs,
        );
    }
}
