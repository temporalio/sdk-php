<?php

declare(strict_types=1);

namespace Temporal\Common;

use JetBrains\PhpStorm\Immutable;

/**
 * Identifies the version(s) of a worker that processed a task
 *
 * @see \Temporal\Api\Common\V1\WorkerVersionStamp
 * @psalm-immutable
 */
#[Immutable]
final class WorkerVersionStamp
{
    public function __construct(
        public string $buildId = '',
        public string $bundleId = '',
        public bool $useVersioning = false,
    ) {
    }
}
