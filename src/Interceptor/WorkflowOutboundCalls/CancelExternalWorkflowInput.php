<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

/**
 * @psalm-immutable
 */
final class CancelExternalWorkflowInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $workflowId,
        public readonly ?string $runId,
    ) {
    }

    public function with(
        ?string $namespace = null,
        ?string $workflowId = null,
        ?string $runId = null,
    ): self {
        return new self(
            $namespace ?? $this->namespace,
            $workflowId ?? $this->workflowId,
            $runId ?? $this->runId,
        );
    }
}
