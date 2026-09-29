<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Lambda;

final class Environment
{
    public function __construct(
        public readonly ?string $runtimeApi = null,
        public readonly ?string $taskRoot = null,
        public readonly ?string $roadRunnerBinary = null,
        public readonly ?string $roadRunnerConfig = null,
        public readonly ?string $shutdownBufferMs = null,
        public readonly ?string $gracefulTimeoutMs = null,
    ) {}

    public static function capture(): self
    {
        return new self(
            runtimeApi: self::read('AWS_LAMBDA_RUNTIME_API'),
            taskRoot: self::read('LAMBDA_TASK_ROOT'),
            roadRunnerBinary: self::read('TEMPORAL_LAMBDA_RR_BINARY'),
            roadRunnerConfig: self::read('TEMPORAL_LAMBDA_RR_CONFIG'),
            shutdownBufferMs: self::read('TEMPORAL_LAMBDA_SHUTDOWN_BUFFER_MS'),
            gracefulTimeoutMs: self::read('TEMPORAL_LAMBDA_GRACEFUL_TIMEOUT_MS'),
        );
    }

    private static function read(string $name): ?string
    {
        $value = \getenv($name);

        return $value === false || $value === '' ? null : $value;
    }
}
