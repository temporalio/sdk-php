<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Exception;

use Temporal\Workflow\WorkflowExecution;

// todo: move to client folder
class WorkflowException extends TemporalException
{
    /**
     * @var WorkflowExecution
     */
    private WorkflowExecution $execution;

    /**
     * @var string|null
     */
    private ?string $type;

    /**
     * @param string|null $message
     * @param WorkflowExecution $execution
     * @param string|null $type
     * @param \Throwable|null $previous
     */
    public function __construct(
        ?string $message,
        WorkflowExecution $execution,
        string $type = null,
        \Throwable $previous = null
    ) {
        if ($message === null) {
            $message = self::buildMessage($execution, $type);
        }

        parent::__construct($message, 0, $previous);
        $this->execution = $execution;
        $this->type = $type;
    }

    /**
     * @return WorkflowExecution
     */
    public function getExecution(): WorkflowExecution
    {
        return $this->execution;
    }

    /**
     * @return string|null
     */
    public function getWorkflowType(): ?string
    {
        return $this->type;
    }

    /**
     * @param WorkflowExecution $execution
     * @param string|null $workflowType
     * @return string
     */
    private static function buildMessage(WorkflowExecution $execution, ?string $workflowType): string
    {
        return "workflowId='"
            . $execution->id
            . "', runId='"
            . $execution->runId
            . ($workflowType == null ? "" : "', workflowType='" . $workflowType . '\'');
    }
}
