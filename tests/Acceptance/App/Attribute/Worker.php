<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Attribute;

use Psr\Log\LoggerInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Worker\WorkerOptions;

/**
 * Customize worker options.
 *
 * The attribute can be used once per TaskQueue.
 *
 * @see \Temporal\Tests\Acceptance\App\Feature\WorkerFactory
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Worker
{
    /**
     * @param array|null $options Callable that returns {@see WorkerOptions}
     * @param array|null $pipelineProvider Callable that returns {@see PipelineProvider}
     * @param array|null $logger Callable that returns {@see LoggerInterface}
     */
    public function __construct(
        public readonly ?array $options = null,
        public readonly ?array $pipelineProvider = null,
        public readonly ?array $logger = null,
    ) {}
}
