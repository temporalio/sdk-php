<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Worker\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spiral\Attributes\AttributeReader;
use Temporal\Common\EnvConfig\Client\ConfigTls;
use Temporal\Common\SdkVersion;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Worker\Core\CoreOptions;
use Temporal\Worker\Core\CoreRole;
use Temporal\Worker\Core\CoreWorkerConfig;
use Temporal\Worker\Core\CoreWorkerFactory;
use Temporal\Worker\WorkerDeploymentOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;
use Temporal\Worker\WorkflowPanicPolicy;

final class CoreWorkerConfigTestCase extends TestCase
{
    private const MAX_CACHED_WORKFLOWS = 50;

    public static function provideBuilds(): iterable
    {
        yield 'all roles with defaults' => [CoreRole::All, WorkerOptions::new(), 1, [
            'namespace' => 'ns',
            'task_queue' => 'queue',
            'workflows' => true,
            'local_activities' => true,
            'remote_activities' => true,
            'deployment' => null,
            'graceful_shutdown_period_ms' => 0,
            'max_worker_activities_per_second' => null,
            'max_cached_workflows' => self::MAX_CACHED_WORKFLOWS,
            'max_outstanding_workflow_tasks' => 100,
            'max_outstanding_activities' => 1,
            'max_concurrent_workflow_task_polls' => 8,
            'sticky_queue_schedule_to_start_timeout_ms' => 5000,
            'max_concurrent_activity_task_polls' => 1,
            'nondeterminism_fails_workflow' => false,
            'max_heartbeat_throttle_interval_ms' => null,
            'poller_autoscaling' => true,
        ]];
        yield 'activity role with concurrency' => [CoreRole::Activity, WorkerOptions::new(), 4, [
            'workflows' => false,
            'remote_activities' => true,
            'max_cached_workflows' => 0,
            'max_outstanding_activities' => 4,
            'max_concurrent_activity_task_polls' => 4,
        ]];
        yield 'workflow role' => [CoreRole::Workflow, WorkerOptions::new(), 4, ['workflows' => true, 'remote_activities' => false, 'max_outstanding_activities' => 1]];
        yield 'disabled workflow worker' => [CoreRole::All, WorkerOptions::new()->withDisableWorkflowWorker(true), 1, ['workflows' => false, 'local_activities' => false, 'max_cached_workflows' => 0]];
        yield 'local activities only' => [CoreRole::All, WorkerOptions::new()->withLocalActivityWorkerOnly(true), 1, ['remote_activities' => false]];
        yield 'explicit options' => [CoreRole::All, WorkerOptions::new()
            ->withWorkerStopTimeout(5)
            ->withStickyScheduleToStartTimeout(2)
            ->withMaxHeartbeatThrottleInterval(3)
            ->withMaxConcurrentWorkflowTaskExecutionSize(1)
            ->withMaxConcurrentWorkflowTaskPollers(1)
            ->withMaxConcurrentActivityTaskPollers(3)
            ->withWorkerActivitiesPerSecond(5.0)
            ->withWorkflowPanicPolicy(WorkflowPanicPolicy::FailWorkflow)
            ->withDeploymentOptions(WorkerDeploymentOptions::new()->withUseVersioning(true)->withVersion('deployment.build')), 1, [
                'deployment' => ['UseVersioning' => true, 'Version' => ['DeploymentName' => 'deployment', 'BuildId' => 'build'], 'DefaultVersioningBehavior' => 0],
                'graceful_shutdown_period_ms' => 5000,
                'max_worker_activities_per_second' => 5.0,
                'max_outstanding_workflow_tasks' => 2,
                'max_concurrent_workflow_task_polls' => 2,
                'sticky_queue_schedule_to_start_timeout_ms' => 2000,
                'max_concurrent_activity_task_polls' => 3,
                'nondeterminism_fails_workflow' => true,
                'max_heartbeat_throttle_interval_ms' => 3000,
            ]];
    }

    #[DataProvider('provideBuilds')]
    public function testBuild(CoreRole $role, WorkerOptions $workerOptions, int $activityConcurrency, array $expected): void
    {
        $config = self::config(self::options(activityConcurrency: $activityConcurrency));

        $build = $config->build(self::worker($workerOptions), $role);

        self::assertSame($expected, \array_intersect_key($build, $expected));
    }

    public static function provideWork(): iterable
    {
        yield 'workflows' => [CoreRole::Workflow, WorkerOptions::new(), true];
        yield 'no workflows' => [CoreRole::Workflow, WorkerOptions::new()->withDisableWorkflowWorker(true), false];
        yield 'activities' => [CoreRole::Activity, WorkerOptions::new(), true];
        yield 'no activities' => [CoreRole::Activity, WorkerOptions::new()->withLocalActivityWorkerOnly(true), false];
    }

    #[DataProvider('provideWork')]
    public function testHasWork(CoreRole $role, WorkerOptions $workerOptions, bool $expected): void
    {
        self::assertSame($expected, self::config(self::options())->hasWork([self::worker($workerOptions)], $role));
    }

    public static function provideStopTimeouts(): iterable
    {
        yield 'no workers' => [[], 10.0];
        yield 'no stop timeout' => [[WorkerOptions::new()], 10.0];
        yield 'longest stop timeout' => [[WorkerOptions::new()->withWorkerStopTimeout(3), WorkerOptions::new()->withWorkerStopTimeout(7)], 17.0];
    }

    #[DataProvider('provideStopTimeouts')]
    public function testStopTimeoutSeconds(array $workerOptions, float $expected): void
    {
        $workers = \array_map(self::worker(...), $workerOptions);

        self::assertSame($expected, self::config(self::options())->stopTimeoutSeconds($workers));
    }

    public static function provideConnections(): iterable
    {
        yield 'plain with process identity' => [null, WorkerOptions::new(), [
            'target_url' => 'http://host:7233',
            'tls' => null,
            'client_name' => SdkVersion::SDK_NAME,
            'identity' => \getmypid() . '@' . \gethostname(),
            'api_key' => 'key',
            'grpc_compression' => 'gzip',
        ]];
        yield 'tls with identity' => [new ConfigTls(serverName: 'server'), WorkerOptions::new()->withIdentity('me'), [
            'target_url' => 'https://host:7233',
            'tls' => ['server_root_ca_cert' => null, 'domain' => 'server', 'client_cert' => null, 'client_private_key' => null],
            'identity' => 'me',
        ]];
    }

    #[DataProvider('provideConnections')]
    public function testConnection(?ConfigTls $tls, WorkerOptions $workerOptions, array $expected): void
    {
        $connection = self::config(self::options(tls: $tls))->connection(self::worker($workerOptions));

        self::assertSame($expected, \array_intersect_key($connection, $expected));
    }

    private static function options(int $activityConcurrency = 1, ?ConfigTls $tls = null): CoreOptions
    {
        $construct = \Closure::bind(static fn(mixed ...$arguments): CoreOptions => new CoreOptions(...$arguments), null, CoreOptions::class);

        return $construct(
            address: 'host:7233',
            namespace: 'ns',
            apiKey: 'key',
            tls: $tls,
            workflowProcesses: 1,
            activityProcesses: 1,
            activityConcurrency: $activityConcurrency,
            maxCachedWorkflows: self::MAX_CACHED_WORKFLOWS,
            grpcCompression: 'gzip',
            pollerAutoscaling: true,
        );
    }

    private static function config(CoreOptions $options): CoreWorkerConfig
    {
        return new CoreWorkerConfig($options, new Marshaller(new AttributeMapperFactory(new AttributeReader())));
    }

    private static function worker(WorkerOptions $options): WorkerInterface
    {
        return CoreWorkerFactory::create()->newWorker('queue', $options);
    }
}
