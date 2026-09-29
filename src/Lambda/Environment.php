<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda;

use Temporal\Lambda\Exception\ConfigurationException;

final class Environment
{
    private const DEFAULT_TASK_ROOT = '/var/task';
    private const DEFAULT_ROAD_RUNNER_BINARY = 'rr';
    private const DEFAULT_SHUTDOWN_BUFFER_MS = 7_000;
    private const DEFAULT_GRACEFUL_TIMEOUT_MS = 5_000;

    public static function capture(): Config
    {
        $runtimeApi = self::runtimeApi();
        if ($runtimeApi === null) {
            throw new ConfigurationException(
                'AWS_LAMBDA_RUNTIME_API is not set: this script must run as a Lambda runtime',
            );
        }

        $taskRoot = self::read('LAMBDA_TASK_ROOT') ?? self::DEFAULT_TASK_ROOT;

        return new Config(
            runtimeApi: $runtimeApi,
            taskRoot: $taskRoot,
            roadRunnerBinary: self::read('TEMPORAL_LAMBDA_RR_BINARY') ?? self::DEFAULT_ROAD_RUNNER_BINARY,
            roadRunnerConfigTemplate: self::read('TEMPORAL_LAMBDA_RR_CONFIG') ?? $taskRoot . '/.rr.yaml',
            shutdownBufferMs: self::milliseconds(
                'TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS',
                self::DEFAULT_SHUTDOWN_BUFFER_MS,
            ),
            gracefulTimeoutMs: self::milliseconds(
                'TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS',
                self::DEFAULT_GRACEFUL_TIMEOUT_MS,
            ),
        );
    }

    public static function runtimeApi(): ?string
    {
        return self::read('AWS_LAMBDA_RUNTIME_API');
    }

    private static function read(string $name): ?string
    {
        $value = \getenv($name);

        return $value === false || $value === '' ? null : $value;
    }

    private static function milliseconds(string $name, int $default): int
    {
        $value = self::read($name);
        if ($value === null) {
            return $default;
        }

        if (!\ctype_digit($value)) {
            throw new ConfigurationException("{$name} must be a positive integer, got \"{$value}\"");
        }

        return (int) $value;
    }
}
