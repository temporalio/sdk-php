<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Interceptor\WorkflowOutboundCalls;

use Temporal\DataConverter\ValuesInterface;

/**
 * @psalm-immutable
 */
final class SignalExternalWorkflowInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $workflowId,
        public readonly ?string $runId,
        public readonly string $signal,
        public readonly ValuesInterface $input,
        public readonly bool $childWorkflowOnly = false,
    ) {
    }

    public function with(
        ?string $namespace = null,
        ?string $workflowId = null,
        ?string $runId = null,
        ?string $signal = null,
        ?ValuesInterface $input = null,
        ?bool $childWorkflowOnly = null,
    ): self {
        return new self(
            $namespace ?? $this->namespace,
            $workflowId ?? $this->workflowId,
            $runId ?? $this->runId,
            $signal ?? $this->signal,
            $input ?? $this->input,
            $childWorkflowOnly ?? $this->childWorkflowOnly,
        );
    }
}
