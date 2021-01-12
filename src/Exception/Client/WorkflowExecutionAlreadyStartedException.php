<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception\Client;

use Temporal\Workflow\WorkflowExecution;

class WorkflowExecutionAlreadyStartedException extends WorkflowException
{
    /**
     * @param WorkflowExecution $execution
     * @param string|null $workflowType
     * @param \Throwable|null $previous
     */
    public function __construct(
        WorkflowExecution $execution,
        string $workflowType = null,
        \Throwable $previous = null
    ) {
        parent::__construct(null, $execution, $workflowType, $previous);
    }
}
