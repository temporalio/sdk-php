<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Client;

use Temporal\Exception\TemporalException;
use Temporal\Workflow\WorkflowExecution;

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
     * @param string|null $workflowType
     * @param \Throwable|null $previous
     */
    public function __construct(
        ?string $message,
        WorkflowExecution $execution,
        string $workflowType = null,
        \Throwable $previous = null
    ) {
        parent::__construct(
            self::buildMessage(
                [
                    'message' => $message,
                    'runId' => $execution->getRunID(),
                    'workflowType' => $workflowType,
                ]
            ),
            0,
            $previous
        );
        $this->execution = $execution;
        $this->type = $workflowType;
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
     * @param \Throwable|null $previous
     * @return WorkflowException
     */
    public static function withoutMessage(
        WorkflowExecution $execution,
        string $workflowType = null,
        \Throwable $previous = null
    ): WorkflowException {
        return new static(null, $execution, $workflowType, $previous);
    }
}
