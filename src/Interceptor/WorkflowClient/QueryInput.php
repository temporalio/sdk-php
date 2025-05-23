<?php

declare(strict_types=1);

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Interceptor\WorkflowClient;

use Temporal\DataConverter\ValuesInterface;
use Temporal\Workflow\WorkflowExecution;

/**
 * @psalm-immutable
 */
class QueryInput
{
    /**
     * @no-named-arguments
     * @internal Don't use the constructor. Use {@see self::with()} instead.
     */
    public function __construct(
        public readonly WorkflowExecution $workflowExecution,
        public readonly ?string $workflowType,
        public readonly string $queryType,
        public readonly ValuesInterface $arguments,
    ) {}

    public function with(
        ?WorkflowExecution $workflowExecution = null,
        ?string $queryType = null,
        ?ValuesInterface $arguments = null,
    ): self {
        return new self(
            $workflowExecution ?? $this->workflowExecution,
            $this->workflowType,
            $queryType ?? $this->queryType,
            $arguments ?? $this->arguments,
        );
    }
}
