<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use Temporal\Workflow\WorkflowExecution;

/**
 * @psalm-immutable
 */
class CancelInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly WorkflowExecution $workflowExecution,
    ) {
    }

    public function with(
        WorkflowExecution $workflowExecution = null,
    ): self {
        return new self(
            $workflowExecution ?? $this->workflowExecution,
        );
    }
}
