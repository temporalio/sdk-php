<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use Temporal\Client\Update\WaitPolicy;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\HeaderInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @psalm-immutable
 */
class UpdateInput
{
    /**
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly WorkflowExecution $workflowExecution,
        public readonly ?string $workflowType,
        public readonly string $updateName,
        public readonly ValuesInterface $arguments,
        public readonly HeaderInterface $header,
        public readonly WaitPolicy $waitPolicy,
        public readonly string $updateId,
        public readonly string $firstExecutionRunId,
        public readonly mixed $resultType,
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
        ?string $updateId = null,
        ?string $firstExecutionRunId = null,
        mixed $resultType = null,
    ): self {
        return new self(
            $workflowExecution ?? $this->workflowExecution,
            $this->workflowType,
            $updateName ?? $this->updateName,
            $arguments ?? $this->arguments,
            $header ?? $this->header,
            $waitPolicy ?? $this->waitPolicy,
            $updateId ?? $this->updateId,
            $firstExecutionRunId ?? $this->firstExecutionRunId,
            $resultType ?? $this->resultType,
        );
    }
}
