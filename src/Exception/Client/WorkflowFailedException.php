<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception\Client;

use Temporal\Workflow\WorkflowExecution;
use Throwable;

class WorkflowFailedException extends WorkflowException
{
    private int $lastWorkflowTaskCompletedEventId;
    private int $retryState;

    /**
     * @param WorkflowExecution $execution
     * @param string|null $workflowType
     * @param int $lastWorkflowTaskCompletedEventId
     * @param int $retryState
     * @param Throwable|null $previous
     */
    public function __construct(
        WorkflowExecution $execution,
        ?string $workflowType,
        int $lastWorkflowTaskCompletedEventId,
        int $retryState,
        \Throwable $previous = null
    ) {
        parent::__construct(
            self::buildMessage(
                [
                    'workflowId' => $execution->id,
                    'runId' => $execution->runId,
                    'workflowType' => $workflowType,
                    'workflowTaskCompletedEventId' => $lastWorkflowTaskCompletedEventId,
                    'retryState' => $retryState
                ]
            ),
            $execution,
            $workflowType,
            $previous
        );

        $this->lastWorkflowTaskCompletedEventId = $lastWorkflowTaskCompletedEventId;
        $this->retryState = $retryState;
    }

    /**
     * @return int
     */
    public function getWorkflowTaskCompletedEventId(): int
    {
        return $this->lastWorkflowTaskCompletedEventId;
    }
}
