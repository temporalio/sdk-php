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
 * @psalm-type SlotsShape = array{min_slots: int, max_slots: int, ramp_throttle_ms: int}
 * @psalm-type TunerShape = array{target_memory_usage: float, target_cpu_usage: float, workflow_slots: SlotsShape, activity_slots: SlotsShape}
 */
final class CoreOptions
{
    private const DEFAULT_ADDRESS = '127.0.0.1:7233';
    private const DEFAULT_PROCESSES = 1;
    private const DEFAULT_ACTIVITY_CONCURRENCY = 1;
    private const DEFAULT_MAX_CACHED_WORKFLOWS = 10_000;
    private const DEFAULT_GRPC_COMPRESSION = 'gzip';
    private const DEFAULT_WORKFLOW_MIN_SLOTS = 5;
    private const DEFAULT_ACTIVITY_MIN_SLOTS = 1;
    private const DEFAULT_MAX_SLOTS = 500;
    private const DEFAULT_WORKFLOW_RAMP_THROTTLE_MS = 0;
    private const DEFAULT_ACTIVITY_RAMP_THROTTLE_MS = 50;

    /**
     * @param TunerShape|null $tuner
     */
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
        public readonly ?array $tuner,
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
            maxCachedWorkflows: CoreEnvironment::integer(CoreEnvironment::MAX_CACHED_WORKFLOWS, self::DEFAULT_MAX_CACHED_WORKFLOWS, 0),
            grpcCompression: CoreEnvironment::string(CoreEnvironment::GRPC_COMPRESSION) ?? self::DEFAULT_GRPC_COMPRESSION,
            pollerAutoscaling: CoreEnvironment::flag(CoreEnvironment::POLLER_AUTOSCALING, true),
            tuner: self::tuner(),
        );
    }

    /**
     * @return TunerShape|null
     */
    private static function tuner(): ?array
    {
        $memory = CoreEnvironment::fraction(CoreEnvironment::TUNER_TARGET_MEMORY_USAGE);
        $cpu = CoreEnvironment::fraction(CoreEnvironment::TUNER_TARGET_CPU_USAGE);
        if ($memory === null && $cpu === null) {
            return null;
        }
        if ($memory === null || $cpu === null) {
            throw new \InvalidArgumentException(\sprintf('%s and %s must be set together', CoreEnvironment::TUNER_TARGET_MEMORY_USAGE, CoreEnvironment::TUNER_TARGET_CPU_USAGE));
        }

        return [
            'target_memory_usage' => $memory,
            'target_cpu_usage' => $cpu,
            'workflow_slots' => self::slots(CoreEnvironment::TUNER_WORKFLOW_MIN_SLOTS, self::DEFAULT_WORKFLOW_MIN_SLOTS, CoreEnvironment::TUNER_WORKFLOW_MAX_SLOTS, CoreEnvironment::TUNER_WORKFLOW_RAMP_THROTTLE, self::DEFAULT_WORKFLOW_RAMP_THROTTLE_MS),
            'activity_slots' => self::slots(CoreEnvironment::TUNER_ACTIVITY_MIN_SLOTS, self::DEFAULT_ACTIVITY_MIN_SLOTS, CoreEnvironment::TUNER_ACTIVITY_MAX_SLOTS, CoreEnvironment::TUNER_ACTIVITY_RAMP_THROTTLE, self::DEFAULT_ACTIVITY_RAMP_THROTTLE_MS),
        ];
    }

    /**
     * @return SlotsShape
     */
    private static function slots(string $min, int $defaultMin, string $max, string $rampThrottle, int $defaultRampThrottle): array
    {
        return [
            'min_slots' => CoreEnvironment::integer($min, $defaultMin, 0),
            'max_slots' => CoreEnvironment::integer($max, self::DEFAULT_MAX_SLOTS, 1),
            'ramp_throttle_ms' => CoreEnvironment::integer($rampThrottle, $defaultRampThrottle, 0),
        ];
    }

    private static function processes(string $name, ?int $argument): int
    {
        return $argument === null
            ? CoreEnvironment::integer($name, self::DEFAULT_PROCESSES, 0)
            : CoreEnvironment::atLeast($name, $argument, 0);
    }
}
