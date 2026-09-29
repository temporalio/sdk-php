<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Temporal\Lambda\Config;
use Temporal\Lambda\Environment;
use Temporal\Lambda\Exception\ConfigurationException;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(Environment::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigurationException::class)]
final class EnvironmentTestCase extends AbstractUnit
{
    private const KEYS = [
        'AWS_LAMBDA_RUNTIME_API',
        'LAMBDA_TASK_ROOT',
        'TEMPORAL_LAMBDA_RR_BINARY',
        'TEMPORAL_LAMBDA_RR_CONFIG',
        'TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS',
        'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS',
    ];

    public function testEveryVariableReachesTheConfig(): void
    {
        \putenv('AWS_LAMBDA_RUNTIME_API=127.0.0.1:9001');
        \putenv('LAMBDA_TASK_ROOT=/opt/task');
        \putenv('TEMPORAL_LAMBDA_RR_BINARY=/opt/rr');
        \putenv('TEMPORAL_LAMBDA_RR_CONFIG=/opt/task/custom.yaml');
        \putenv('TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS=9000');
        \putenv('TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS=6000');

        $config = Environment::capture();

        self::assertSame('127.0.0.1:9001', $config->runtimeApi);
        self::assertSame('/opt/task', $config->taskRoot);
        self::assertSame('/opt/rr', $config->roadRunnerBinary);
        self::assertSame('/opt/task/custom.yaml', $config->roadRunnerConfigTemplate);
        self::assertSame(9_000, $config->shutdownBufferMs);
        self::assertSame(6_000, $config->gracefulTimeoutMs);
    }

    public function testDefaultsApplyWhenOnlyTheRuntimeApiIsSet(): void
    {
        \putenv('AWS_LAMBDA_RUNTIME_API=127.0.0.1:9001');

        $config = Environment::capture();

        self::assertSame('/var/task', $config->taskRoot);
        self::assertSame('rr', $config->roadRunnerBinary);
        self::assertSame('/var/task/.rr.yaml', $config->roadRunnerConfigTemplate);
        self::assertSame(7_000, $config->shutdownBufferMs);
        self::assertSame(5_000, $config->gracefulTimeoutMs);
    }

    public function testTheConfigTemplateFollowsTheTaskRoot(): void
    {
        \putenv('AWS_LAMBDA_RUNTIME_API=127.0.0.1:9001');
        \putenv('LAMBDA_TASK_ROOT=/opt/task');

        self::assertSame('/opt/task/.rr.yaml', Environment::capture()->roadRunnerConfigTemplate);
    }

    public function testAMissingRuntimeApiIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'AWS_LAMBDA_RUNTIME_API is not set: this script must run as a Lambda runtime',
        );

        Environment::capture();
    }

    public function testAnEmptyVariableIsTreatedAsUnset(): void
    {
        \putenv('AWS_LAMBDA_RUNTIME_API=');

        $this->expectException(ConfigurationException::class);

        Environment::capture();
    }

    public function testANonNumericTimeoutIsRejected(): void
    {
        \putenv('AWS_LAMBDA_RUNTIME_API=127.0.0.1:9001');
        \putenv('TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS=5s');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS must be a positive integer, got "5s"',
        );

        Environment::capture();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearEnvironment();
    }

    protected function tearDown(): void
    {
        $this->clearEnvironment();

        parent::tearDown();
    }

    private function clearEnvironment(): void
    {
        foreach (self::KEYS as $key) {
            \putenv($key);
        }
    }
}
