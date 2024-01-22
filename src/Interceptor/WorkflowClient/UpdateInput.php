<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Workflow\Update\WaitPolicy;
use Temporal\Workflow\WorkflowExecution;

/**
 * @psalm-immutable
 */
class UpdateInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly WorkflowExecution $workflowExecution,
        public readonly ?string $workflowType,
        public readonly string $updateName,
        public readonly ValuesInterface $arguments,
        public readonly HeaderInterface $header,
        public readonly WaitPolicy $waitPolicy,
        public readonly ?string $firstExecutionRunId,
    ) {
    }

    /**
     * @param string|null $firstExecutionRunId Set empty string to reset.
     */
    public function with(
        ?WorkflowExecution $workflowExecution = null,
        ?string $updateName = null,
        ?ValuesInterface $arguments = null,
        ?HeaderInterface $header = null,
        ?WaitPolicy $waitPolicy = null,
        ?string $firstExecutionRunId = null,
    ): self {
        return new self(
            $workflowExecution ?? $this->workflowExecution,
            $this->workflowType,
            $updateName ?? $this->updateName,
            $arguments ?? $this->arguments,
            $header ?? $this->header,
            $waitPolicy ?? $this->waitPolicy,
            match ($firstExecutionRunId) {
                null => $this->firstExecutionRunId,
                '' => null,
                default => $firstExecutionRunId,
            }
        );
    }
}
