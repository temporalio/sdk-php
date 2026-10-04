<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Bridge;

/**
 * @internal
 */
final class CoreEnvironment
{
    public const BRIDGE_LIB = 'TEMPORAL_CORE_BRIDGE_LIB';
    public const THREADS = 'TEMPORAL_CORE_THREADS';
    public const LOG = 'TEMPORAL_CORE_LOG';
    public const ROLE = 'TEMPORAL_CORE_ROLE';
    public const WORKFLOW_PROCESSES = 'TEMPORAL_CORE_WORKFLOW_PROCESSES';
    public const ACTIVITY_PROCESSES = 'TEMPORAL_CORE_ACTIVITY_PROCESSES';
    public const ACTIVITY_CONCURRENCY = 'TEMPORAL_CORE_ACTIVITY_CONCURRENCY';
    public const MAX_CACHED_WORKFLOWS = 'TEMPORAL_CORE_MAX_CACHED_WORKFLOWS';
    public const GRPC_COMPRESSION = 'TEMPORAL_CORE_GRPC_COMPRESSION';
    public const PROFILE = 'TEMPORAL_CORE_PROFILE';
    public const PROMETHEUS = 'TEMPORAL_CORE_PROMETHEUS_ADDRESS';
    public const POLLER_AUTOSCALING = 'TEMPORAL_CORE_POLLER_AUTOSCALING';

    public static function string(string $name): ?string
    {
        /** @var scalar|null $value */
        $value = $_SERVER[$name] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    public static function integer(string $name, int $default, int $min): int
    {
        $value = self::string($name);

        return $value === null ? $default : self::atLeast($name, $value, $min);
    }

    public static function atLeast(string $name, int|string $value, int $min): int
    {
        $integer = \filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => $min]]);
        if ($integer === false) {
            throw new \InvalidArgumentException(\sprintf('%s must be an integer not less than %d, "%s" given', $name, $min, $value));
        }

        return $integer;
    }

    public static function flag(string $name, bool $default = false): bool
    {
        $value = self::string($name);
        if ($value === null) {
            return $default;
        }

        $flag = \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE);
        if ($flag === null) {
            throw new \InvalidArgumentException(\sprintf('%s must be a boolean, "%s" given', $name, $value));
        }

        return $flag;
    }
}
