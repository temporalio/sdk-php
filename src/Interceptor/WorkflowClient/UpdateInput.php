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
        public readonly string $updateType,
        public readonly ValuesInterface $arguments,
        public readonly HeaderInterface $header,
    ) {
    }

    public function with(
        ?WorkflowExecution $workflowExecution = null,
        ?string $updateType = null,
        ?ValuesInterface $arguments = null,
        ?HeaderInterface $header = null,
    ): self {
        return new self(
            $workflowExecution ?? $this->workflowExecution,
            $this->workflowType,
            $updateType ?? $this->updateType,
            $arguments ?? $this->arguments,
            $header ?? $this->header,
        );
    }
}
