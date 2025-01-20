<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Attribute;

use Temporal\Common\RetryOptions as CommonOptions;

/**
 * An attribute to configure workflow stub.
 *
 * @see \Temporal\Tests\Acceptance\App\Feature\WorkflowStubInjector
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Stub
{
    public CommonOptions $retryOptions;

    /**
     * @param non-empty-string $type Workflow type.
     * @param non-empty-string|null $workflowId
     * @param list<mixed> $args
     */
    public function __construct(
        public string $type,
        public bool $eagerStart = false,
        public ?string $workflowId = null,
        public ?string $executionTimeout = null,
        public array $args = [],
        public array $memo = [],
        RetryOptions $retryOptions = new RetryOptions(),
    ) {
        $this->retryOptions = $retryOptions->toRetryOptions();
    }
}
