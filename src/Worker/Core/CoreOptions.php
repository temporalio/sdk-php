<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Revolt\EventLoop;
use Temporal\Internal\Bridge\CoreEnvironment;
use Temporal\Client\ClientOptions;
use Temporal\Common\EnvConfig\Client\ConfigTls;
use Temporal\Common\EnvConfig\ConfigClient;
use Temporal\Worker\ServiceCredentials;

/**
 * @internal
 */
final class CoreOptions
{
    private const DEFAULT_ADDRESS = '127.0.0.1:7233';
    private const DEFAULT_PROCESSES = 1;
    private const DEFAULT_ACTIVITY_CONCURRENCY = 1;
    private const DEFAULT_MAX_CACHED_WORKFLOWS = 10_000;
    private const MIN_DEFAULT_CACHED_WORKFLOWS = 10;
    private const MEMORY_RESERVE_BYTES = 64 * 1024 * 1024;
    private const CACHED_WORKFLOW_BYTES = 64 * 1024;
    private const DEFAULT_GRPC_COMPRESSION = 'gzip';

    private function __construct(
        public readonly string $address,
        public readonly string $namespace,
        public readonly ?string $apiKey,
        public readonly ?ConfigTls $tls,
        public readonly int $workflowProcesses,
        public readonly int $activityProcesses,
        public readonly int $activityConcurrency,
        public readonly int $maxCachedWorkflows,
        public readonly string $grpcCompression,
        public readonly bool $pollerAutoscaling,
    ) {}

    /**
     * @psalm-suppress InternalClass, InternalMethod, InternalProperty
     */
    public static function create(
        ?string $address,
        ?string $namespace,
        ?ServiceCredentials $credentials,
        ?int $workflowProcesses,
        ?int $activityProcesses,
    ): self {
        $activityConcurrency = CoreEnvironment::integer(CoreEnvironment::ACTIVITY_CONCURRENCY, self::DEFAULT_ACTIVITY_CONCURRENCY, 1);
        if ($activityConcurrency > 1 && !\class_exists(EventLoop::class)) {
            throw new \InvalidArgumentException(\sprintf('%s > 1 needs the revolt/event-loop package', CoreEnvironment::ACTIVITY_CONCURRENCY));
        }
        $configProfile = ConfigClient::load();
        $apiKey = ($credentials?->apiKey ?? '') ?: $configProfile->apiKey;
        $tls = $configProfile->tlsConfig;

        return new self(
            address: $address ?? $configProfile->address ?? self::DEFAULT_ADDRESS,
            namespace: $namespace ?? $configProfile->namespace ?? ClientOptions::DEFAULT_NAMESPACE,
            apiKey: $apiKey === null ? null : (string) $apiKey,
            tls: $tls === null || $tls->disabled ? null : $tls,
            workflowProcesses: self::processes(CoreEnvironment::WORKFLOW_PROCESSES, $workflowProcesses),
            activityProcesses: self::processes(CoreEnvironment::ACTIVITY_PROCESSES, $activityProcesses),
            activityConcurrency: $activityConcurrency,
            maxCachedWorkflows: CoreEnvironment::integer(CoreEnvironment::MAX_CACHED_WORKFLOWS, self::defaultMaxCachedWorkflows((string) \ini_get('memory_limit')), 0),
            grpcCompression: CoreEnvironment::string(CoreEnvironment::GRPC_COMPRESSION) ?? self::DEFAULT_GRPC_COMPRESSION,
            pollerAutoscaling: CoreEnvironment::flag(CoreEnvironment::POLLER_AUTOSCALING, true),
        );
    }

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    public static function defaultMaxCachedWorkflows(string $memoryLimit): int
    {
        $bytes = \ini_parse_quantity($memoryLimit);
        if ($bytes <= 0) {
            return self::DEFAULT_MAX_CACHED_WORKFLOWS;
        }

        return \max(
            self::MIN_DEFAULT_CACHED_WORKFLOWS,
            \min(self::DEFAULT_MAX_CACHED_WORKFLOWS, \intdiv($bytes - self::MEMORY_RESERVE_BYTES, self::CACHED_WORKFLOW_BYTES)),
        );
    }

    private static function processes(string $name, ?int $argument): int
    {
        return $argument === null
            ? CoreEnvironment::integer($name, self::DEFAULT_PROCESSES, 0)
            : CoreEnvironment::atLeast($name, $argument, 0);
    }
}
