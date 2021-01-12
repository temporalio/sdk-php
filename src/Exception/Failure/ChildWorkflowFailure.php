<?php


namespace Temporal\Exception\Failure;


use Temporal\Workflow\WorkflowExecution;

class ChildWorkflowFailure extends TemporalFailure
{
    /**
     * @var int
     */
    private int $initiatedEventId;

    /**
     * @var int
     */
    private int $startedEventId;

    /**
     * @var string
     */
    private string $namespace;

    /**
     * @var int
     */
    private int $retryState;

    /**
     * @var WorkflowExecution
     */
    private WorkflowExecution $execution;

    /**
     * @var string
     */
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
                    'workflowId' => $execution->id,
                    'runId' => $execution->runId,
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
