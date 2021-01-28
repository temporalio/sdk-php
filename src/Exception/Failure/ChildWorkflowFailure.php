<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Exception\Failure;

use Temporal\Workflow\WorkflowExecution;

class ChildWorkflowFailure extends TemporalFailure
{
    private int $initiatedEventId;
    private int $startedEventId;
    private string $namespace;
    private int $retryState;
    private WorkflowExecution $execution;
    private string $workflowType;

    /**
     * @param int $initiatedEventId
     * @param int $startedEventId
     * @param string $workflowType
     * @param WorkflowExecution $execution
     * @param string $namespace
     * @param int $retryState
     * @param \Throwable|null $previous
     */
    public function __construct(
        int $initiatedEventId,
        int $startedEventId,
        string $workflowType,
        WorkflowExecution $execution,
        string $namespace,
        int $retryState,
        \Throwable $previous = null
    ) {
        parent::__construct(
            self::buildMessage(
                [
                    'workflowId' => $execution->getID(),
                    'runId' => $execution->getRunID(),
                    'workflowType' => $workflowType,
                    'initiatedEventId' => $initiatedEventId,
                    'startedEventId' => $startedEventId,
                    'namespace' => $namespace,
                    'retryState' => $retryState,
                ]
            ),
            null,
            $previous
        );

        $this->initiatedEventId = $initiatedEventId;
        $this->startedEventId = $startedEventId;
        $this->workflowType = $workflowType;
        $this->execution = $execution;
        $this->namespace = $namespace;
        $this->retryState = $retryState;
    }

    /**
     * @return int
     */
    public function getInitiatedEventId(): int
    {
        return $this->initiatedEventId;
    }

    /**
     * @return int
     */
    public function getStartedEventId(): int
    {
        return $this->startedEventId;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return int
     */
    public function getRetryState(): int
    {
        return $this->retryState;
    }

    /**
     * @return WorkflowExecution
     */
    public function getExecution(): WorkflowExecution
    {
        return $this->execution;
    }

    /**
     * @return string
     */
    public function getWorkflowType(): string
    {
        return $this->workflowType;
    }
}
