<?php

namespace Temporal\Exception\Client;

use Temporal\Exception\WorkflowException;
use Temporal\Workflow\WorkflowExecution;

class WorkflowExecutionAlreadyStartedException extends WorkflowException
{
    /**
     * WorkflowExecutionAlreadyStartedException constructor.
     * @param WorkflowExecution $execution
     * @param string|null $type
     * @param \Throwable|null $previous
     */
    public function __construct(
        WorkflowExecution $execution,
        string $type = null,
        \Throwable $previous = null
    ) {
        parent::__construct(null, $execution, $type, $previous);
    }
}
