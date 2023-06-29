<?php

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use JetBrains\PhpStorm\Immutable;
use Temporal\Interceptor\HeaderInterface;

/**
 * @psalm-immutable
 */
#[Immutable]
final class CancelExternalWorkflowInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public string $namespace,
        #[Immutable]
        public string $workflowId,
        #[Immutable]
        public ?string $runId,
        #[Immutable]
        public HeaderInterface $header,
    ) {
    }

    public function with(
        ?string $namespace = null,
        ?string $workflowId = null,
        ?string $runId = null,
        ?HeaderInterface $header = null,
    ): self {
        return new self(
            $namespace ?? $this->namespace,
            $workflowId ?? $this->workflowId,
            $runId ?? $this->runId,
            $header ?? $this->header,
        );
    }
}
