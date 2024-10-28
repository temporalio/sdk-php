<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Temporal\Workflow\WorkflowExecution;

class WorkflowQueryRejectedException extends WorkflowQueryException
{
    private int $queryRejectCondition;

    private int $workflowExecutionStatus;

    public function __construct(
        WorkflowExecution $execution,
        string $type,
        int $queryRejectCondition,
        int $workflowExecutionStatus,
        \Throwable $previous = null,
    ) {
        parent::__construct(null, $execution, $type, $previous);
        $this->queryRejectCondition = $queryRejectCondition;
        $this->workflowExecutionStatus = $workflowExecutionStatus;
    }

    public function getQueryRejectCondition(): int
    {
        return $this->queryRejectCondition;
    }

    public function getWorkflowExecutionStatus(): int
    {
        return $this->workflowExecutionStatus;
    }
}
