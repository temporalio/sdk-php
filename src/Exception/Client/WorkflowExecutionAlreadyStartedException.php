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

class WorkflowExecutionAlreadyStartedException extends WorkflowException
{
    /**
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
