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

    public function __construct(
        int $initiatedEventId,
        int $startedEventId,
        string $workflowType,
        WorkflowExecution $execution,
        string $namespace,
        int $retryState,
        \Throwable $previous = null,
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
                ],
            ),
            null,
            $previous,
        );

        $this->initiatedEventId = $initiatedEventId;
        $this->startedEventId = $startedEventId;
        $this->workflowType = $workflowType;
        $this->execution = $execution;
        $this->namespace = $namespace;
        $this->retryState = $retryState;
    }

    public function getInitiatedEventId(): int
    {
        return $this->initiatedEventId;
    }

    public function getStartedEventId(): int
    {
        return $this->startedEventId;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getRetryState(): int
    {
        return $this->retryState;
    }

    public function getExecution(): WorkflowExecution
    {
        return $this->execution;
    }

    public function getWorkflowType(): string
    {
        return $this->workflowType;
    }
}
