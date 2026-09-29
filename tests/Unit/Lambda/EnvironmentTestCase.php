<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Lambda;

use PHPUnit\Framework\Attributes\CoversClass;
use Temporal\Lambda\Environment;
use Temporal\Tests\Unit\AbstractUnit;

#[CoversClass(Environment::class)]
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

    public function testEveryVariableIsCaptured(): void
    {
        \putenv('AWS_LAMBDA_RUNTIME_API=127.0.0.1:9001');
        \putenv('LAMBDA_TASK_ROOT=/var/task');
        \putenv('TEMPORAL_LAMBDA_RR_BINARY=/opt/rr');
        \putenv('TEMPORAL_LAMBDA_RR_CONFIG=/var/task/custom.yaml');
        \putenv('TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS=9000');
        \putenv('TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS=6000');

        $environment = Environment::capture();

        self::assertSame('127.0.0.1:9001', $environment->runtimeApi);
        self::assertSame('/var/task', $environment->taskRoot);
        self::assertSame('/opt/rr', $environment->roadRunnerBinary);
        self::assertSame('/var/task/custom.yaml', $environment->roadRunnerConfig);
        self::assertSame('9000', $environment->shutdownBufferMs);
        self::assertSame('6000', $environment->gracefulTimeoutMs);
    }

    public function testUnsetVariablesBecomeNull(): void
    {
        $environment = Environment::capture();

        self::assertNull($environment->runtimeApi);
        self::assertNull($environment->taskRoot);
        self::assertNull($environment->roadRunnerBinary);
        self::assertNull($environment->roadRunnerConfig);
        self::assertNull($environment->shutdownBufferMs);
        self::assertNull($environment->gracefulTimeoutMs);
    }

    public function testAnEmptyVariableIsTreatedAsUnset(): void
    {
        \putenv('AWS_LAMBDA_RUNTIME_API=');

        self::assertNull(Environment::capture()->runtimeApi);
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
