<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Attribute;

/**
 * An attribute to configure client options.
 *
 * @see \Temporal\Tests\Acceptance\App\Feature\WorkflowStubInjector
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Client
{
    public function __construct(
        public float|null $timeout = null,
        public \Closure|array|string|null $pipelineProvider = null,
        public array $payloadConverters = [],
    ) {}
}
