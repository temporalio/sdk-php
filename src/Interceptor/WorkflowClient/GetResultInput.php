<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use JetBrains\PhpStorm\Immutable;
use Temporal\Workflow\WorkflowExecution;

/**
 * @psalm-immutable
 */
#[Immutable]
class GetResultInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        #[Immutable]
        public WorkflowExecution $workflowExecution,
        #[Immutable]
        public ?string $workflowType,
        #[Immutable]
        public ?int $timeout,
        #[Immutable]
        public mixed $type,
    ) {
    }

    public function with(
        WorkflowExecution $workflowExecution = null,
        string $workflowType = null,
        int $timeout = null,
    ): self {
        return new self(
            $workflowExecution ?? $this->workflowExecution,
            $workflowType ?? $this->workflowType,
            $timeout ?? $this->timeout,
            $this->type,
        );
    }
}
