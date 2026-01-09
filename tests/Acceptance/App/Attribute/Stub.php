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
    public readonly ?CommonOptions $retryOptions;

    /**
     * @param non-empty-string $type Workflow type.
     * @param non-empty-string|null $workflowId
     * @param list<mixed> $args
     */
    public function __construct(
        public string $type,
        /**
         * @see WorkflowOptions::withEagerStart()
         */
        public bool $eagerStart = false,
        /**
         * @see WorkflowOptions::withWorkflowId()
         */
        public ?string $workflowId = null,
        /**
         * @see WorkflowOptions::withWorkflowExecutionTimeout()
         */
        public ?string $executionTimeout = null,
        public array $args = [],
        /**
         * @see WorkflowOptions::withMemo()
         */
        public array $memo = [],
        /**
         * @see WorkflowOptions::withRetryOptions()
         */
        ?RetryOptions $retryOptions = null,
    ) {
        $this->retryOptions = $retryOptions?->toRetryOptions();
    }
}
