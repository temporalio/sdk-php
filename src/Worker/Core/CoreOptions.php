<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

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
    private const DEFAULT_GRPC_COMPRESSION = 'gzip';
    private const ENV_WORKFLOW_PROCESSES = 'TEMPORAL_CORE_WORKFLOW_PROCESSES';
    private const ENV_ACTIVITY_PROCESSES = 'TEMPORAL_CORE_ACTIVITY_PROCESSES';
    private const ENV_ACTIVITY_CONCURRENCY = 'TEMPORAL_CORE_ACTIVITY_CONCURRENCY';
    private const ENV_MAX_CACHED_WORKFLOWS = 'TEMPORAL_CORE_MAX_CACHED_WORKFLOWS';
    private const ENV_GRPC_COMPRESSION = 'TEMPORAL_CORE_GRPC_COMPRESSION';
    private const ENV_PROFILE = 'TEMPORAL_CORE_PROFILE';

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
        public readonly bool $profile,
    ) {}

    public static function create(
        ?string $address,
        ?string $namespace,
        ?ServiceCredentials $credentials,
        ?int $workflowProcesses,
        ?int $activityProcesses,
    ): self {
        $profile = ConfigClient::load();
        $apiKey = $credentials?->apiKey ?: $profile->apiKey;
        $tls = $profile->tlsConfig;

        return new self(
            address: $address ?? $profile->address ?? self::DEFAULT_ADDRESS,
            namespace: $namespace ?? $profile->namespace ?? ClientOptions::DEFAULT_NAMESPACE,
            apiKey: $apiKey === null ? null : (string) $apiKey,
            tls: $tls === null || $tls->disabled ? null : $tls,
            workflowProcesses: $workflowProcesses ?? self::integer(self::ENV_WORKFLOW_PROCESSES, self::DEFAULT_PROCESSES, 0),
            activityProcesses: $activityProcesses ?? self::integer(self::ENV_ACTIVITY_PROCESSES, self::DEFAULT_PROCESSES, 0),
            activityConcurrency: self::integer(self::ENV_ACTIVITY_CONCURRENCY, self::DEFAULT_ACTIVITY_CONCURRENCY, 1),
            maxCachedWorkflows: self::integer(self::ENV_MAX_CACHED_WORKFLOWS, self::DEFAULT_MAX_CACHED_WORKFLOWS, 0),
            grpcCompression: (string) ($_SERVER[self::ENV_GRPC_COMPRESSION] ?? self::DEFAULT_GRPC_COMPRESSION),
            profile: (bool) ($_SERVER[self::ENV_PROFILE] ?? false),
        );
    }

    private static function integer(string $name, int $default, int $min): int
    {
        $value = $_SERVER[$name] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }

        $integer = \filter_var($value, \FILTER_VALIDATE_INT);
        if ($integer === false || $integer < $min) {
            throw new \InvalidArgumentException(\sprintf('%s must be an integer not less than %d, "%s" given', $name, $min, $value));
        }

        return $integer;
    }
}
