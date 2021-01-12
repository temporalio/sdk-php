<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception\Client;

use Temporal\Api\Enums\V1\RetryState;
use Temporal\Workflow\WorkflowExecution;
use Throwable;

class WorkflowFailedException extends WorkflowException
{
    /**
     * @var int
     */
    private int $lastWorkflowTaskCompletedEventId;

    /**
     * @var int
     */
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
            self::buildMessage($execution, $type, $lastWorkflowTaskCompletedEventId, $retryState),
            $execution,
            $type,
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

    /**
     * @param WorkflowExecution $workflowExecution
     * @param string $workflowType
     * @param int $workflowTaskCompletedEventId
     * @param int $retryState
     * @return string
     */
    public static function buildMessage(
        WorkflowExecution $workflowExecution,
        string $workflowType,
        int $workflowTaskCompletedEventId,
        int $retryState
    ): string {
        return "workflowId='"
            . $workflowExecution->id
            . "', runId='"
            . $workflowExecution->runId
            . ($workflowType == null ? "'" : "', workflowType='" . $workflowType . '\'')
            . ", retryState="
            . RetryState::name($retryState)
            . ", workflowTaskCompletedEventId="
            . $workflowTaskCompletedEventId;
    }
}
