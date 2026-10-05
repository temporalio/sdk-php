<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temporal\Internal\Bridge\CoreEnvironment;
use Temporal\Worker\Core\CoreOptions;
use Temporal\Worker\ServiceCredentials;

final class CoreOptionsTestCase extends TestCase
{
    private const NO_DEFAULT_PROFILE = "[profile.other]\naddress = \"other:7233\"";
    private const PROFILE_ENVIRONMENT = ['TEMPORAL_ADDRESS' => 'profile:7233', 'TEMPORAL_NAMESPACE' => 'profile-ns', 'TEMPORAL_API_KEY' => 'profile-key'];

    public static function provideOptions(): iterable
    {
        yield 'defaults' => [[], [], [], [
            'address' => '127.0.0.1:7233',
            'namespace' => 'default',
            'apiKey' => null,
            'tls' => null,
            'workflowProcesses' => 1,
            'activityProcesses' => 1,
            'activityConcurrency' => 1,
            'maxCachedWorkflows' => 10_000,
            'grpcCompression' => 'gzip',
            'pollerAutoscaling' => true,
            'tuner' => null,
        ]];
        yield 'arguments' => [[], [], [
            'address' => 'arg:7233',
            'namespace' => 'arg-ns',
            'credentials' => ServiceCredentials::create()->withApiKey('own-key'),
            'workflowProcesses' => 2,
            'activityProcesses' => 0,
        ], ['address' => 'arg:7233', 'namespace' => 'arg-ns', 'apiKey' => 'own-key', 'workflowProcesses' => 2, 'activityProcesses' => 0]];
        yield 'core environment' => [[], [
            CoreEnvironment::WORKFLOW_PROCESSES => '3',
            CoreEnvironment::ACTIVITY_PROCESSES => '4',
            CoreEnvironment::ACTIVITY_CONCURRENCY => '5',
            CoreEnvironment::MAX_CACHED_WORKFLOWS => '0',
            CoreEnvironment::GRPC_COMPRESSION => 'none',
            CoreEnvironment::POLLER_AUTOSCALING => 'false',
        ], [], [
            'workflowProcesses' => 3,
            'activityProcesses' => 4,
            'activityConcurrency' => 5,
            'maxCachedWorkflows' => 0,
            'grpcCompression' => 'none',
            'pollerAutoscaling' => false,
        ]];
        yield 'resource-based tuner defaults' => [[], [
            CoreEnvironment::TUNER_TARGET_MEMORY_USAGE => '0.8',
            CoreEnvironment::TUNER_TARGET_CPU_USAGE => '0.9',
        ], [], ['tuner' => [
            'target_memory_usage' => 0.8,
            'target_cpu_usage' => 0.9,
            'workflow_slots' => ['min_slots' => 5, 'max_slots' => 500, 'ramp_throttle_ms' => 0],
            'activity_slots' => ['min_slots' => 1, 'max_slots' => 500, 'ramp_throttle_ms' => 50],
        ]]];
        yield 'resource-based tuner slots' => [[], [
            CoreEnvironment::TUNER_TARGET_MEMORY_USAGE => '1',
            CoreEnvironment::TUNER_TARGET_CPU_USAGE => '0',
            CoreEnvironment::TUNER_WORKFLOW_MIN_SLOTS => '2',
            CoreEnvironment::TUNER_WORKFLOW_MAX_SLOTS => '20',
            CoreEnvironment::TUNER_WORKFLOW_RAMP_THROTTLE => '10',
            CoreEnvironment::TUNER_ACTIVITY_MIN_SLOTS => '0',
            CoreEnvironment::TUNER_ACTIVITY_MAX_SLOTS => '30',
            CoreEnvironment::TUNER_ACTIVITY_RAMP_THROTTLE => '0',
        ], [], ['tuner' => [
            'target_memory_usage' => 1.0,
            'target_cpu_usage' => 0.0,
            'workflow_slots' => ['min_slots' => 2, 'max_slots' => 20, 'ramp_throttle_ms' => 10],
            'activity_slots' => ['min_slots' => 0, 'max_slots' => 30, 'ramp_throttle_ms' => 0],
        ]]];
        yield 'profile' => [self::PROFILE_ENVIRONMENT, [], [], [
            'address' => 'profile:7233',
            'namespace' => 'profile-ns',
            'apiKey' => 'profile-key',
        ]];
        yield 'credentials key wins over the profile key' => [self::PROFILE_ENVIRONMENT, [], ['credentials' => ServiceCredentials::create()->withApiKey('own-key')], ['apiKey' => 'own-key']];
        yield 'empty credentials key keeps the profile key' => [self::PROFILE_ENVIRONMENT, [], ['credentials' => ServiceCredentials::create()], ['apiKey' => 'profile-key']];
        yield 'disabled tls' => [self::PROFILE_ENVIRONMENT + ['TEMPORAL_TLS' => 'false'], [], [], ['tls' => null]];
    }

    #[DataProvider('provideOptions')]
    public function testCreate(array $environment, array $server, array $arguments, array $expected): void
    {
        $options = $this->create($environment, $server, $arguments);

        self::assertSame($expected, \array_intersect_key(\get_object_vars($options), $expected));
    }

    public function testProfileTlsIsKept(): void
    {
        $options = $this->create(self::PROFILE_ENVIRONMENT + ['TEMPORAL_TLS_SERVER_NAME' => 'server'], [], []);

        self::assertSame('server', $options->tls?->serverName);
    }

    public static function provideMemoryLimits(): iterable
    {
        yield 'unlimited' => ['-1', 10_000];
        yield '2G is capped at the unlimited default' => ['2G', 10_000];
        yield '512M' => ['512M', 7_168];
        yield '128M' => ['128M', 1_024];
        yield '96M in bytes' => ['100663296', 512];
        yield '64M leaves nothing above the reserve' => ['64M', 10];
        yield '16M' => ['16M', 10];
    }

    #[DataProvider('provideMemoryLimits')]
    public function testDefaultCacheSizeFollowsTheMemoryLimit(string $memoryLimit, int $expected): void
    {
        self::assertSame($expected, CoreOptions::defaultMaxCachedWorkflows($memoryLimit));
    }

    public function testMemoryLimitChangesOnlyTheDefaultCacheSize(): void
    {
        $previous = (string) \ini_get('memory_limit');
        self::assertNotFalse(\ini_set('memory_limit', '512M'));
        try {
            self::assertSame(7_168, $this->create([], [], [])->maxCachedWorkflows);
            self::assertSame(20_000, $this->create([], [CoreEnvironment::MAX_CACHED_WORKFLOWS => '20000'], [])->maxCachedWorkflows);
        } finally {
            \ini_set('memory_limit', $previous);
        }
    }

    public function testNegativeProcessCountIsRejected(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException(CoreEnvironment::ACTIVITY_PROCESSES . ' must be an integer not less than 0, "-1" given'));
        $this->create([], [], ['activityProcesses' => -1]);
    }

    public function testTunerTargetsMustBeSetTogether(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException(CoreEnvironment::TUNER_TARGET_MEMORY_USAGE . ' and ' . CoreEnvironment::TUNER_TARGET_CPU_USAGE . ' must be set together'));
        $this->create([], [CoreEnvironment::TUNER_TARGET_CPU_USAGE => '0.9'], []);
    }

    private function create(array $environment, array $server, array $arguments): CoreOptions
    {
        $environment += ['TEMPORAL_CONFIG_FILE' => self::NO_DEFAULT_PROFILE];
        $previous = [];
        foreach ($environment as $name => $value) {
            $previous[$name] = \getenv($name);
            \putenv("$name=$value");
        }
        $_SERVER = $server + $_SERVER;
        try {
            return CoreOptions::create(
                $arguments['address'] ?? null,
                $arguments['namespace'] ?? null,
                $arguments['credentials'] ?? null,
                $arguments['workflowProcesses'] ?? null,
                $arguments['activityProcesses'] ?? null,
            );
        } finally {
            foreach ($previous as $name => $value) {
                \putenv($value === false ? $name : "$name=$value");
            }
            foreach (\array_keys($server) as $name) {
                unset($_SERVER[$name]);
            }
        }
    }
}
