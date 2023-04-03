<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use JetBrains\PhpStorm\Immutable;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @psalm-immutable
 */
#[Immutable]
class QueryInput
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
        public string $queryType,
        #[Immutable]
        public ValuesInterface $arguments,
        // #[Immutable]
        // public Class<R> $resultClass,
        // #[Immutable]
        // public Type $resultType,
    ) {
    }

    public function with(
        WorkflowExecution $workflowExecution = null,
        string $queryType = null,
        ValuesInterface $arguments = null,
        // Class<R> $resultClass = null,
        // Type $resultType = null,
    ): self {
        return new self(
            $workflowExecution ?? $this->workflowExecution,
            $this->workflowType,
            $queryType ?? $this->queryType,
            $arguments ?? $this->arguments,
            // $resultClass ?? $this->resultClass,
            // $resultType ?? $this->resultType,
        );
    }
}
