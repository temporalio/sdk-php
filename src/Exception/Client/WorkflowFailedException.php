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
     * @param string|null $type
     * @param int $lastWorkflowTaskCompletedEventId
     * @param int $retryState
     * @param Throwable|null $previous
     */
    public function __construct(
        WorkflowExecution $execution,
        ?string $type,
        int $lastWorkflowTaskCompletedEventId,
        int $retryState,
        \Throwable $previous = null
    ) {
        parent::__construct(
            null,
            $execution,
            $type,
            $previous
        );

        $this->message = self::buildMessage(
            [
                'workflowId' => $execution->id,
                'runId' => $execution->runId,
                'workflowType' => $type,
                'workflowTaskCompletedEventId' => $lastWorkflowTaskCompletedEventId,
                'retryState' => $retryState
            ]
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
